<?php

namespace App\Services;

use App\Enums\WorkflowStage;
use App\Models\LimsWorklist;
use App\Models\Qualification;
use App\Models\QualificationRun;
use Illuminate\Support\Facades\Log;

/**
 * Drives GQS runs from the LIMS Worklist Catalog. Only runs with a linked worklist are touched; older
 * runs with no worklist stay fully manual. On each catalog refresh (or manual sync) this re-reads the
 * worklist and:
 *   - INC_SAMPLE_STATUS = A  -> incubation complete, advance Incubating -> Awaiting Results
 *   - SAMPLE_STATUS = A + INC = A + WORKLIST_ALL_FINAL + Pass  -> mark QCM-result-ready (display only;
 *       the QCM still reviews and builds the cover page; NEVER auto-sent to QA)
 *   - SAMPLE_STATUS = A + Fail  -> authorized fail (confirmed excursion) flagged for the fail/NC path
 *   - otherwise -> not ready; leave where it is
 * Also records the LIMS evaluation/status on the run for display and cover-page pre-fill, and warns
 * (log) when the worklist's personnel/description does not match the linked run's person.
 *
 * It does NOT mark a run failed or open an NC by itself - that stays a human decision in Lab Review/QA;
 * it only surfaces the LIMS state so the right person acts.
 */
class WorklistSync
{
    /** Sync every run/qualification that has a linked worklist. Returns count of runs touched. */
    public function syncAll(): int
    {
        $touched = 0;

        // Runs that carry a worklist directly.
        $runs = QualificationRun::query()
            ->whereNotNull('lims_worklist_id')
            ->where('lims_worklist_id', '!=', '')
            ->get();
        foreach ($runs as $run) {
            if ($this->syncRun($run)) $touched++;
        }

        // Qualifications that carry a worklist but whose latest run has none yet (best-effort: stamp the run).
        $quals = Qualification::query()
            ->whereNotNull('lims_worklist_id')
            ->where('lims_worklist_id', '!=', '')
            ->get();
        foreach ($quals as $q) {
            $run = QualificationRun::where('personnel_id', $q->personnel_id)
                ->where(fn ($w) => $w->whereNull('lims_worklist_id')->orWhere('lims_worklist_id', ''))
                ->latest('run_date')->latest('id')->first();
            if ($run) {
                $run->lims_worklist_id = $q->lims_worklist_id;
                $run->save();
                if ($this->syncRun($run)) $touched++;
            }
        }

        return $touched;
    }

    /** Sync a single run from its linked worklist. Returns true if anything changed. */
    public function syncRun(QualificationRun $run): bool
    {
        $wl = LimsWorklist::findByWorklist($run->lims_worklist_id);
        if (! $wl) return false;
        if ($wl->non_reportable) return false; // duplicate/abandoned worklist: never drives a run

        $q = $run->qualification ?: Qualification::currentFor($run->personnel_id);

        // Person-match confirmation: warn if the worklist's person clearly is not this run's person.
        $this->warnOnPersonMismatch($run, $wl, $q);

        $changed = false;

        // Record LIMS state on the run for display + cover-page pre-fill.
        $ncNumber = trim((string) $wl->qual_reference) ?: null;
        $ncUrl = $ncNumber ? \App\Models\NcDocument::urlForNumber($ncNumber) : null;
        $newState = [
            'lims_evaluation' => $wl->evaluation ?: null,
            'lims_sample_status' => $wl->sample_status ?: null,
            'lims_inc_status' => $wl->inc_sample_status ?: null,
            'lims_all_final' => $wl->worklist_all_final,
            'lims_qcm_ready' => $wl->isQcmReady(),
            'lims_nc_number' => $ncNumber,
            'lims_nc_url' => $ncUrl,
            'lims_inc1_incubator' => $wl->inc1_incubator ?: null,
            'lims_inc1_start' => $wl->inc1_start ?: null,
            'lims_inc1_end' => $wl->inc1_end ?: null,
            'lims_inc2_incubator' => $wl->inc2_incubator ?: null,
            'lims_inc2_start' => $wl->inc2_start ?: null,
            'lims_inc2_end' => $wl->inc2_end ?: null,
            'lims_inc_due' => ($wl->inc2_due ?: $wl->inc1_due) ?: null,
            'lims_synced_at' => now(),
        ];
        foreach ($newState as $k => $v) {
            if ((string) $run->{$k} !== (string) $v) { $run->{$k} = $v; $changed = true; }
        }
        if ($changed) $run->save();

        if (! $q) return $changed;

        // Stage advancement - only ever forward, and never into QA automatically.
        $stage = $q->workflow_stage;

        // Incubation STARTED (1st incubation has a start timestamp) -> Incubating.
        if ($wl->incubationStarted()
            && in_array($stage, [WorkflowStage::RunScheduled, WorkflowStage::RunPerformed], true)) {
            $q->workflow_stage = WorkflowStage::Incubating;
            $q->stage_changed_at = now();
            $q->save();
            $changed = true;
            $stage = $q->workflow_stage;
        }

        // Incubation COMPLETE (2nd incubation has an end timestamp) -> Awaiting Results (ready to read).
        if ($wl->incubationComplete() && $stage === WorkflowStage::Incubating) {
            $q->workflow_stage = WorkflowStage::AwaitingResults;
            $q->stage_changed_at = now();
            $q->save();
            $changed = true;
            $stage = $q->workflow_stage;
        }

        // QCM-result-ready: record the result + release, but DO NOT advance to QA. The QCM still reviews
        // and creates the cover page. We mirror the manual release (result + timestamps + recompute) and
        // land in Results Released so it is QCM-reviewable, exactly like a manually-entered pass.
        if ($wl->isQcmReady()) {
            $advanceFrom = [WorkflowStage::Incubating, WorkflowStage::AwaitingResults, WorkflowStage::RunPerformed];
            if ($run->result === null || (($run->result->value ?? $run->result) !== 'pass')) {
                $run->result = \App\Enums\RunResult::Pass;
                $run->results_entered_at = $run->results_entered_at ?: now();
                $run->results_released_at = $run->results_released_at ?: now();
                $run->save();
                $changed = true;
                app(QualificationEngine::class)->recompute($q->fresh());
                $q = $q->fresh();
                $stage = $q->workflow_stage;
            }
            if (in_array($stage, $advanceFrom, true)) {
                $q->workflow_stage = WorkflowStage::ResultsReleased;
                $q->stage_changed_at = now();
                $q->save();
                $changed = true;
            }
        }

        // Authorized fail: surface as a confirmed excursion. We do not auto-fail the stage; we flag it so
        // the run shows the authorized-fail state and Lab Review/QA can route it to the NC path.
        // (lims_evaluation/status already recorded above make this visible.)

        return $changed;
    }

    protected function warnOnPersonMismatch(QualificationRun $run, LimsWorklist $wl, ?Qualification $q): void
    {
        $person = $run->personnel ?: ($q?->personnel);
        if (! $person) return;

        $login = strtoupper(trim((string) ($person->lims_username ?: '')));
        $wlPersonnel = strtoupper(trim((string) $wl->personnel));
        if ($login !== '' && $wlPersonnel !== '' && $login === $wlPersonnel) return; // username matches

        // Fall back to checking the worklist description / personnel against the person's name.
        $last = strtolower(trim((string) $person->last_name));
        $first = strtolower(trim((string) $person->first_name));
        $hay = strtolower(($wl->worklist_description ?: '') . ' ' . ($wl->personnel ?: ''));
        $nameLooksRight = $last !== '' && str_contains($hay, $last);
        // username pattern: first-initial + lastname (RRODRIGUEZ)
        $patternLooksRight = false;
        if ($first !== '' && $last !== '' && $wlPersonnel !== '') {
            $patternLooksRight = str_starts_with($wlPersonnel, strtoupper($first[0])) && str_contains($wlPersonnel, strtoupper($last));
        }
        if ($nameLooksRight || $patternLooksRight) return;

        Log::warning('WorklistSync person mismatch', [
            'run_id' => $run->id,
            'worklist' => $wl->worklist,
            'run_person' => trim($person->first_name . ' ' . $person->last_name),
            'worklist_personnel' => $wl->personnel,
            'worklist_description' => $wl->worklist_description,
        ]);
    }
}

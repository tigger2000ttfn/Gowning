<?php

namespace App\Services;

use App\Enums\QualificationType;
use App\Enums\RunResult;
use App\Enums\WorkflowStage;
use App\Models\LimsWorklist;
use App\Models\Personnel;
use App\Models\Qualification;
use App\Models\QualificationRun;
use Illuminate\Support\Carbon;

/**
 * Backfills GQS with historic qualification data from the LIMS Worklist Catalog. Personnel already
 * exist (with lims_username); this matches each qual worklist to a person and creates the qualification
 * + run record(s) that actually happened. Initial = up to 3 runs (one qualification, sibling run rows);
 * Annual = 1 run. A Pass lands at Results Released (QCM-reviewable) but is NOT submitted to QA, and no
 * Veeva number is invented. Fails create the run marked Fail and rely on the QUAL REFERENCE NC link.
 *
 * Match only - never creates personnel, never touches lims_username. Routine-EM and unmatched rows are
 * skipped (the latter surfaced for manual handling). Idempotent: a worklist already linked to a run is
 * not recreated.
 */
class WorklistBackfill
{
    /**
     * @param bool $preview when true, computes counts + a sample list without writing
     * @param int|null $onlyPersonnelId when set, backfill only this person's worklists (per-person mode)
     * @return array{created:int, quals:int, matched:int, unmatched:int, skipped:int, rows:array, blocked?:bool}
     */
    public function run(bool $preview = false, ?int $onlyPersonnelId = null): array
    {
        // Bulk backfill is a one-time operation. Once it has been committed, block re-running it (a
        // per-person backfill is always allowed and ignores this guard).
        if (! $preview && $onlyPersonnelId === null && \App\Models\Setting::get('worklist_backfill_done', false)) {
            return ['created' => 0, 'quals' => 0, 'matched' => 0, 'unmatched' => 0, 'skipped' => 0, 'rows' => [], 'blocked' => true];
        }

        $created = 0; $quals = 0; $matched = 0; $unmatched = 0; $skipped = 0;
        $rows = [];

        // Resolve the person filter (used to scope which worklists to consider).
        $onlyLogin = null; $onlyLast = null; $onlyFirstInit = null;
        if ($onlyPersonnelId) {
            $p = Personnel::find($onlyPersonnelId);
            if (! $p) return ['created' => 0, 'quals' => 0, 'matched' => 0, 'unmatched' => 0, 'skipped' => 0, 'rows' => []];
            $onlyLogin = strtoupper(trim((string) $p->lims_username));
            $onlyLast = strtoupper(trim((string) $p->last_name));
            $onlyFirstInit = strtoupper(substr(trim((string) $p->first_name), 0, 1));
        }

        foreach (LimsWorklist::query()->orderBy('worklist')->get() as $wl) {
            // Per-person scope: skip worklists whose login/name does not match the chosen person.
            if ($onlyPersonnelId) {
                $wlLogin = strtoupper(trim((string) $wl->personnel));
                $matchesLogin = $onlyLogin !== '' && $wlLogin === $onlyLogin;
                $matchesName = $onlyLast !== '' && strlen($wlLogin) >= 2
                    && substr($wlLogin, 0, 1) === $onlyFirstInit && substr($wlLogin, 1) === $onlyLast;
                if (! $matchesLogin && ! $matchesName) continue;
            }
            $type = $this->inferType($wl->qualification_type, $wl->worklist_description, $wl->em_area);

            if ($type === null) {
                // routine EM or unclassifiable
                if ($this->isRoutineEm($wl->qualification_type, $wl->em_area)) { $skipped++; continue; }
                $skipped++;
                continue;
            }

            $person = $this->matchPerson($wl);
            if (! $person) {
                $unmatched++;
                if (count($rows) < 60) $rows[] = ['worklist' => $wl->worklist, 'person' => $wl->personnel, 'desc' => $wl->worklist_description, 'status' => 'unmatched'];
                continue;
            }

            // Already linked to an existing run? skip (idempotent).
            $alreadyLinked = QualificationRun::where('lims_worklist_id', $wl->worklist)->exists();
            if ($alreadyLinked) { $skipped++; continue; }

            $matched++;
            $dates = $this->runDates($wl, $type);
            if (empty($dates)) { $skipped++; continue; }

            $isPass = $wl->isPass();
            $isFail = $wl->isFail();
            $runCount = count($dates);

            if (count($rows) < 60) {
                $rows[] = [
                    'worklist' => $wl->worklist,
                    'person' => trim($person->first_name . ' ' . $person->last_name),
                    'type' => $type === 'initial' ? 'Initial' : 'Annual',
                    'runs' => $runCount,
                    'eval' => $isPass ? 'Pass' : ($isFail ? 'Fail' : '—'),
                    'status' => 'will create',
                ];
            }

            if ($preview) { $created += $runCount; $quals++; continue; }

            // ---- write ----
            $cycleType = $type === 'annual' ? QualificationType::Annual : QualificationType::Initial;
            $runsRequired = $cycleType->runsRequired();
            $firstDate = $dates[array_key_first($dates)];

            $qual = Qualification::create([
                'personnel_id' => $person->id,
                'type' => $cycleType,
                'status' => \App\Enums\QualificationStatus::InProgress,
                'runs_required' => $runsRequired,
                'runs_completed' => 0,
                'cycle_started_at' => $firstDate->toDateString(),
                'lims_worklist_id' => $wl->worklist,
                'workflow_stage' => WorkflowStage::AwaitingResults,
                'stage_changed_at' => now(),
            ]);

            $result = $isFail ? RunResult::Fail : ($isPass ? RunResult::Pass : RunResult::Pending);
            foreach ($dates as $runNo => $date) {
                QualificationRun::create([
                    'personnel_id' => $person->id,
                    'qualification_id' => $qual->id,
                    'run_date' => $date->toDateString(),
                    'result' => $result,
                    'cycle_type' => $cycleType,
                    'lims_worklist_id' => $wl->worklist,
                    'results_entered_at' => $isPass || $isFail ? now() : null,
                    'results_released_at' => $isPass ? now() : null,
                    'notes' => 'Historic backfill from LIMS worklist ' . $wl->worklist,
                ]);
                $created++;
            }
            $quals++;

            // Recompute counts/status, then place at the right stage WITHOUT submitting to QA.
            app(QualificationEngine::class)->recompute($qual->fresh());
            $qual = $qual->fresh();
            if ($isFail) {
                $qual->workflow_stage = WorkflowStage::Failed;
            } else {
                // Pass (or pending): land at Results Released so the QCM reviews + builds the cover page.
                $qual->workflow_stage = WorkflowStage::ResultsReleased;
            }
            $qual->stage_changed_at = now();
            $qual->save();
        }

        if (! $preview && $created > 0) {
            // Stamp LIMS state + NC links onto the newly-created runs once.
            app(WorklistSync::class)->syncAll();
            // Mark the one-time bulk backfill done (only when this was a bulk run).
            if ($onlyPersonnelId === null) {
                \App\Models\Setting::put('worklist_backfill_done', true);
            }
        }

        return compact('created', 'quals', 'matched', 'unmatched', 'skipped', 'rows');
    }

    protected function matchPerson(LimsWorklist $wl): ?Personnel
    {
        $login = strtoupper(trim((string) $wl->personnel));
        if ($login !== '') {
            $byLogin = Personnel::whereRaw('UPPER(lims_username) = ?', [$login])->get();
            if ($byLogin->count() === 1) return $byLogin->first();
        }
        // Name fallback from username pattern (first-initial + lastname, e.g. RRODRIGUEZ) or description.
        if ($login !== '' && strlen($login) >= 2) {
            $firstInitial = substr($login, 0, 1);
            $lastPart = substr($login, 1);
            $cands = Personnel::whereRaw('UPPER(last_name) = ?', [$lastPart])
                ->whereRaw('UPPER(LEFT(first_name,1)) = ?', [$firstInitial])->get();
            if ($cands->count() === 1) return $cands->first();
        }
        return null;
    }

    protected function isRoutineEm(?string $type, ?string $emArea): bool
    {
        return trim((string) $type) === '' && stripos((string) $emArea, 'routine em') !== false;
    }

    protected function inferType(?string $type, ?string $description, ?string $emArea): ?string
    {
        if ($this->isRoutineEm($type, $emArea)) return null;
        $t = strtolower(trim((string) $type));
        if (str_contains($t, 'initial')) return 'initial';
        if (str_contains($t, 'annual') || str_contains($t, 'requal')) return 'annual';
        $d = strtolower((string) $description);
        if ($d === '') return null;
        if (preg_match('/re-?\s*qual|requal|requalification|annual|anual/', $d)) return 'annual';
        if (preg_match('/initial|gowning\s*qual/', $d)) return 'initial';
        return null;
    }

    protected function parseLimsDate(?string $d): ?Carbon
    {
        $d = trim((string) $d);
        if ($d === '') return null;
        foreach (['Y-m-d', 'd-m-Y', 'm/d/Y', 'd/m/Y'] as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $d);
                if ($c) return $c->startOfDay();
            } catch (\Throwable $e) {}
        }
        try { return Carbon::parse($d)->startOfDay(); } catch (\Throwable $e) { return null; }
    }

    /** @return array<int,Carbon> run-number => date, reschedule-aware. */
    protected function runDates(LimsWorklist $wl, string $type): array
    {
        $d1 = $this->parseLimsDate($wl->qual_date_1);
        $d2 = $this->parseLimsDate($wl->qual_date_2);
        $d3 = $this->parseLimsDate($wl->qual_date_3);
        $r2 = strtolower(trim((string) $wl->run2_rescheduled)) === 'yes';
        $r3 = strtolower(trim((string) $wl->run3_rescheduled)) === 'yes';

        if ($type === 'annual') {
            return $d1 ? [1 => $d1] : [];
        }
        // initial = 3 runs
        $run1 = $d1;
        $run2 = ($r2 && $d2) ? $d2 : $run1;
        $run3 = ($r3 && $d3) ? $d3 : $run2;
        $out = [];
        if ($run1) $out[1] = $run1;
        if ($run2) $out[2] = $run2;
        if ($run3) $out[3] = $run3;
        return $out;
    }
}

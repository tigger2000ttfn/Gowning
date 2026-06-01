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
            // Non-reportable (duplicate/abandoned): never link to a person, never create anything.
            if ($wl->non_reportable) { $skipped++; continue; }
            // Per-person scope: skip worklists whose login/name does not match the chosen person.
            if ($onlyPersonnelId) {
                $wlLogin = strtoupper(trim((string) $wl->personnel));
                $matchesLogin = $onlyLogin !== '' && $wlLogin === $onlyLogin;
                $matchesName = $onlyLast !== '' && strlen($wlLogin) >= 2
                    && substr($wlLogin, 0, 1) === $onlyFirstInit && substr($wlLogin, 1) === $onlyLast;
                if (! $matchesLogin && ! $matchesName) continue;
            }
            $type = $this->typeFor($wl);

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

            // ---- write (atomic per worklist) ----
            // Wrap each worklist's qualification + run rows in a transaction so a failure midway
            // through ONE worklist rolls back cleanly instead of leaving half-created records. We
            // capture how many run rows this worklist added so the running total stays accurate.
            $cycleType = $type === 'annual' ? QualificationType::Annual : QualificationType::Initial;
            $runsRequired = $cycleType->runsRequired();
            $firstDate = $dates[array_key_first($dates)];

            try {
                $createdThisWorklist = \Illuminate\Support\Facades\DB::transaction(function () use ($wl, $person, $cycleType, $runsRequired, $firstDate, $dates, $type, $isFail, $isPass) {
                    $added = 0;
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
                            'incubation_started_at' => $date->toDateString(),
                            'results_entered_at' => $isPass || $isFail ? now() : null,
                            'results_released_at' => $isPass ? now() : null,
                            'notes' => 'Historic backfill from LIMS worklist ' . $wl->worklist,
                        ]);
                        $added++;
                    }

                    // Recompute counts/status, then place at the right stage WITHOUT submitting to QA.
                    app(QualificationEngine::class)->recompute($qual->fresh());
                    $qual = $qual->fresh();
                    $passCount = $qual->runs()->where('result', RunResult::Pass->value)->count();
                    if ((int) $qual->runs_completed !== $passCount) {
                        $qual->runs_completed = $passCount;
                    }
                    if ($isFail) {
                        $qual->workflow_stage = WorkflowStage::Failed;
                    } else {
                        $qual->workflow_stage = WorkflowStage::ResultsReleased;
                    }
                    $qual->stage_changed_at = now();

                    // Inferred classroom completion (only when none already exists), dated to the worklist's
                    // QUAL DATE 1 (the first run date is the same for an initial cycle). QA edits these later
                    // and can add the LMS number on the Class Completions page.
                    if (! \App\Models\ClassCompletion::where('personnel_id', $person->id)->exists()) {
                        $classDate = ($this->parseLimsDate($wl->qual_date_1) ?: $firstDate)->toDateString();
                        \App\Models\ClassCompletion::create([
                            'personnel_id' => $person->id,
                            'employee_id' => $person->employee_id,
                            'class_name' => 'Gowning Qualification Class (Inferred)',
                            'completion_date' => $classDate,
                            'source' => 'inferred',
                        ]);
                        $qual->class_on_file = true;
                        $qual->class_on_file_date = $classDate;
                    }
                    $qual->save();
                    return $added;
                });
                $created += $createdThisWorklist;
                $quals++;
            } catch (\Throwable $e) {
                // One worklist failed and was rolled back (no partial rows). Record it and keep going
                // so a single bad row never aborts the whole backfill or leaves a mess behind.
                report($e);
                $rows[] = [
                    'worklist' => $wl->worklist,
                    'person' => $person->full_name ?? '-',
                    'type' => $type,
                    'status' => 'ERROR: ' . \Illuminate\Support\Str::limit($e->getMessage(), 160),
                ];
                $skipped++;
            }
        }

        if (! $preview && $created > 0) {
            // Link multi-cycle history per person: order each person's backfilled qualifications by
            // cycle start, number them 1..N, parent-link each to the prior, and mark all but the latest
            // as superseded so currentFor() resolves to the most recent cycle.
            $this->linkCycles($onlyPersonnelId);

            // Stamp LIMS state + NC links onto the newly-created runs once.
            app(WorklistSync::class)->syncAll();
            // Mark the one-time bulk backfill done (only when this was a bulk run).
            if ($onlyPersonnelId === null) {
                \App\Models\Setting::put('worklist_backfill_done', true);
            }
        }

        return compact('created', 'quals', 'matched', 'unmatched', 'skipped', 'rows');
    }

    /**
     * After backfilling, a person can have several independent qualification rows (one per historic
     * worklist). Turn those into a proper cycle chain: oldest cycle = 1, each later cycle parent-links to
     * the prior and increments cycle_number, and every cycle except the most recent is marked superseded
     * so Qualification::currentFor() returns the latest. Only touches backfill-created rows (those whose
     * runs are all historic-backfill rows) and never demotes a QA/QCM-signed or Qualified cycle.
     *
     * @param int|null $onlyPersonnelId when set, only relink that person's cycles
     */
    protected function linkCycles(?int $onlyPersonnelId = null): void
    {
        $personIds = Qualification::query()
            ->when($onlyPersonnelId, fn ($q) => $q->where('personnel_id', $onlyPersonnelId))
            ->whereNotNull('lims_worklist_id')
            ->distinct()->pluck('personnel_id');

        foreach ($personIds as $pid) {
            $quals = Qualification::where('personnel_id', $pid)
                ->orderByRaw('COALESCE(cycle_started_at, created_at) asc')
                ->orderBy('id')
                ->get();
            if ($quals->count() < 2) {
                // Single cycle: just make sure it is the current one (cycle 1, not superseded).
                $only = $quals->first();
                if ($only && ($only->cycle_number !== 1 || $only->superseded_at !== null)) {
                    $only->forceFill(['cycle_number' => 1, 'superseded_at' => null, 'parent_qualification_id' => null])->saveQuietly();
                }
                continue;
            }

            $cycle = 0; $prevId = null; $last = $quals->count() - 1;
            foreach ($quals->values() as $i => $q) {
                $cycle++;
                $isLatest = $i === $last;
                $q->forceFill([
                    'cycle_number' => $cycle,
                    'parent_qualification_id' => $prevId,
                    // supersede every cycle except the most recent one
                    'superseded_at' => $isLatest ? null : ($q->superseded_at ?? now()),
                ])->saveQuietly();
                $prevId = $q->id;
            }
        }
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

    /**
     * Authoritative type from the worklist's structured LIMS columns. Per the LIMS export, a row carries
     * exactly one of: INITIAL GOWNING QUALIFICATION RUN # (initial, 3 runs), ANNUAL REQUALIFICATION
     * (annual, 1 run), or ADDITIONAL REQUALIFICATION (treated as annual, 1 run). We trust these columns
     * over the free-text type/description so an annual requal is never created as a 3-run initial.
     */
    protected function typeFor(LimsWorklist $wl): ?string
    {
        $hasInitial = trim((string) $wl->initial_run_no) !== '';
        $hasAnnual = trim((string) $wl->annual_requal) !== '';
        $hasAdditional = trim((string) $wl->additional_requal) !== '';

        if ($hasAnnual || $hasAdditional) return 'annual';   // 1 run
        if ($hasInitial) return 'initial';                   // 3 runs
        // Fall back to the free-text type/description heuristic only when no structured column is set.
        return $this->inferType($wl->qualification_type, $wl->worklist_description, $wl->em_area);
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

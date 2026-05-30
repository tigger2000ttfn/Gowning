<?php

namespace App\Services;

use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Holistic, multi-run aware stage driver.
 *
 * A person may need 1 run (annual/requal) or 3 runs (initial/lapsed). Each run is
 * performed separately, its plates incubate separately. The PERSON does not advance
 * to "results" until the LAST required run's plates have cleared incubation. Incubation
 * is a transient per-run state; the person's workflow waits on the final run.
 *
 * Rules implemented:
 *  - performedRuns  = runs in this cycle that have been performed (incubation_started_at set)
 *  - all performed runs must clear their incubation window before results are releasable
 *  - if performed < required: the person still owes runs. After the current run's plates
 *    clear, they go back to "ready to schedule the next run" (RunScheduled if a future
 *    reservation exists, else ClassComplete so the auto-scheduler re-books them).
 *  - if performed == required AND every run has cleared incubation: AwaitingResults.
 */
class RunCycleAdvancer
{
    public function incubationDays(): int
    {
        return (int) Setting::get('incubation_days', 8);
    }

    /** Performed runs in the current cycle (anchored at cycle_started_at if present). */
    public function cycleRuns(Qualification $q)
    {
        $query = QualificationRun::where('personnel_id', $q->personnel_id)
            ->whereNotNull('incubation_started_at');
        if ($q->cycle_started_at) {
            $query->whereDate('run_date', '>=', CarbonImmutable::parse($q->cycle_started_at)->toDateString());
        }
        return $query->orderBy('run_date')->orderBy('id')->get();
    }

    /** Has a single run's incubation window elapsed? */
    public function runCleared(QualificationRun $run): bool
    {
        if (! $run->incubation_started_at) {
            return false;
        }
        return now()->greaterThanOrEqualTo(
            CarbonImmutable::parse($run->incubation_started_at)->addDays($this->incubationDays())
        );
    }

    /**
     * Recompute the person's workflow stage from their cycle runs.
     * Called after marking a run performed and by the daily incubation job.
     * Returns the stage it settled on (or null if no change was warranted).
     */
    public function advance(Qualification $q): ?WorkflowStage
    {
        // Only operate while the person is in the run/incubation portion of the pipeline.
        $inRunPhase = in_array($q->workflow_stage, [
            WorkflowStage::RunScheduled, WorkflowStage::RunPerformed,
            WorkflowStage::Incubating, WorkflowStage::AwaitingResults,
        ], true);
        if (! $inRunPhase) {
            return null;
        }

        $required = max(1, (int) $q->runs_required);
        $runs = $this->cycleRuns($q);
        $performed = $runs->count();

        if ($performed === 0) {
            return null; // nothing performed yet; leave as-is (RunScheduled etc.)
        }

        $allCleared = $runs->every(fn ($r) => $this->runCleared($r));
        $latest = $runs->last();
        $latestCleared = $latest ? $this->runCleared($latest) : false;

        $target = null;

        if ($performed >= $required && $allCleared) {
            // Final required run's plates are in; ready to read/release for the person.
            $target = WorkflowStage::AwaitingResults;
        } elseif ($performed < $required) {
            // Still owe runs. While the just-performed run incubates, stay Incubating.
            // Once it clears, the person is ready for their next run.
            $target = $latestCleared ? $this->readyForNextRunStage($q) : WorkflowStage::Incubating;
        } else {
            // performed == required but not all cleared yet -> still incubating.
            $target = WorkflowStage::Incubating;
        }

        if ($target && $q->workflow_stage !== $target) {
            $q->workflow_stage = $target;
            $q->stage_changed_at = now();
            $q->save();
            return $target;
        }
        return $q->workflow_stage;
    }

    /**
     * When a person still owes runs and the current run cleared: are they already
     * booked for the next run? If a future approved reservation exists -> RunScheduled,
     * otherwise drop to ClassComplete so the auto-scheduler re-books them.
     */
    protected function readyForNextRunStage(Qualification $q): WorkflowStage
    {
        $hasFutureBooking = \App\Models\Reservation::where('personnel_id', $q->personnel_id)
            ->whereIn('status', ['requested', 'approved'])
            ->whereHas('runSlot', fn ($s) => $s->whereDate('slot_date', '>=', now()->toDateString()))
            ->exists();

        return $hasFutureBooking ? WorkflowStage::RunScheduled : WorkflowStage::ClassComplete;
    }

    /** Sweep all in-run-phase qualifications. Returns count advanced. Used by the daily job. */
    public function sweep(): int
    {
        $moved = 0;
        $candidates = Qualification::whereIn('workflow_stage', [
            WorkflowStage::Incubating->value,
            WorkflowStage::RunPerformed->value,
        ])->get();
        foreach ($candidates as $q) {
            $before = $q->workflow_stage;
            $after = $this->advance($q);
            if ($after && $after !== $before) {
                $moved++;
            }
        }
        return $moved;
    }
}

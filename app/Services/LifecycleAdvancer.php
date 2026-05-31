<?php

namespace App\Services;

use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * The yearly gowning lifecycle automation.
 *
 *  - A qualified person past their due date (+ grace) automatically becomes a
 *    3-run requalification (lapsed = treated like initial for run count).
 *  - The gowning class is taken ONCE, ever. class_on_file persists across cycles,
 *    so a requal does NOT send them back to Class Pending: they start at Class
 *    Complete (ready to book runs) because the class is still on file.
 *  - Only QA (failure determination) can clear class_on_file to require retraining.
 */
class LifecycleAdvancer
{
    public function graceDays(): int
    {
        return (int) Setting::get('grace_days', 0);
    }

    public function initialRuns(): int
    {
        return (int) Setting::get('initial_runs_required', 3);
    }

    public function lapsedRuns(): int
    {
        return (int) Setting::get('lapsed_runs_required', $this->initialRuns());
    }

    /** How many days before the due date a requalification is auto-kicked-off. */
    public function requalWindowDays(): int
    {
        return (int) Setting::get('requal_window_days', 30);
    }

    public function annualRuns(): int
    {
        return (int) Setting::get('annual_runs_required', 1);
    }

    /**
     * Nightly lifecycle pass:
     *  1. Kick off requalification 30 days (configurable) before the due date - the person stays
     *     Qualified (access intact) but enters the run workflow so they can complete a 1-run requal
     *     in time. Marked with requal_started_at so it does not re-kick each night.
     *  2. Lapse anyone past their due date (+ grace) into a forced 3-run requalification and revoke
     *     their qualified status (no cleanroom access until they requalify, per QA/SOP).
     *
     * Returns the count of records lapsed (for the command summary).
     */
    public function run(): int
    {
        $grace = $this->graceDays();
        $window = $this->requalWindowDays();
        $lapsed = 0;

        $qualified = Qualification::where('status', 'qualified')
            ->whereNotNull('due_date')
            ->whereNull('superseded_at')
            ->get();

        foreach ($qualified as $q) {
            $due = CarbonImmutable::parse($q->due_date);
            $cutoff = $due->addDays($grace)->endOfDay();

            if (now()->greaterThan($cutoff)) {
                // PAST DUE -> lapsed: forced full 3-run requalification, qualified status revoked.
                $q->status = 'lapsed';
                $q->type = 'initial';
                $q->runs_required = $this->lapsedRuns();
                $q->runs_completed = 0;
                $q->cycle_started_at = now()->toDateString(); // fresh requal cycle
                $q->qualified_date = null;
                // class persists: if on file, skip straight to ready-to-book; else needs class
                $q->workflow_stage = $q->class_on_file
                    ? WorkflowStage::ClassComplete
                    : WorkflowStage::ClassPending;
                $q->stage_changed_at = now();
                $q->save();
                \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::Lapsed, ['personnel' => $q->personnel, 'qualification' => $q]);
                $lapsed++;
                continue;
            }

            // WITHIN THE REQUAL WINDOW (<= window days to due, not past due) and not yet kicked off:
            // start the annual (1-run) requalification while keeping them Qualified until the due date.
            $windowOpens = $due->subDays($window)->startOfDay();
            $alreadyInRequal = in_array($q->workflow_stage?->value, [
                'run_scheduled', 'run_performed', 'incubating', 'awaiting_results', 'results_released', 'qa_review',
            ], true);
            if (! $q->requal_started_at && now()->greaterThanOrEqualTo($windowOpens) && ! $alreadyInRequal) {
                $q->type = 'annual';
                $q->runs_required = $this->annualRuns();
                $q->runs_completed = 0;
                $q->cycle_started_at = now()->toDateString();
                $q->requal_started_at = now();
                // status STAYS qualified (still valid + cleanroom access until the due date).
                // class is on file from the prior cycle -> ready to book the requal run.
                $q->workflow_stage = $q->class_on_file
                    ? WorkflowStage::ClassComplete
                    : WorkflowStage::ClassPending;
                $q->stage_changed_at = now();
                $q->save();
                \App\Services\AutomationEngine::fire(\App\Enums\AutomationTrigger::DueSoon, ['personnel' => $q->personnel, 'qualification' => $q]);
            }
        }
        return $lapsed;
    }
}

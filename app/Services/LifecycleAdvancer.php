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

    /** Scan qualified records and lapse any past due (+grace) into a 3-run requal. Returns count. */
    public function run(): int
    {
        $grace = $this->graceDays();
        $lapsed = 0;

        $qualified = Qualification::where('status', 'qualified')
            ->whereNotNull('due_date')
            ->get();

        foreach ($qualified as $q) {
            $cutoff = CarbonImmutable::parse($q->due_date)->addDays($grace)->endOfDay();
            if (now()->greaterThan($cutoff)) {
                // lapsed: full 3-run requalification
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
            }
        }
        return $lapsed;
    }
}

<?php

namespace App\Services;

use App\Enums\WorkflowStage;
use App\Models\Qualification;
use App\Models\QualificationRun;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Time-based automation: once the incubation period elapses from the run-performed
 * date, a card moves Incubating -> Awaiting Results on its own (no human action).
 * Plates are then ready to read; entering results moves it to Results Released.
 */
class IncubationAdvancer
{
    public function incubationDays(): int
    {
        return (int) Setting::get('incubation_days', 8);
    }

    /** Promote any qualifications whose incubation period has elapsed. Returns count moved. */
    public function run(): int
    {
        $days = $this->incubationDays();
        $moved = 0;

        $incubating = Qualification::where('workflow_stage', WorkflowStage::Incubating->value)->get();
        foreach ($incubating as $q) {
            $run = QualificationRun::where('personnel_id', $q->personnel_id)
                ->latest('run_date')->latest('id')->first();
            $started = $run?->incubation_started_at;
            if (! $started) {
                continue;
            }
            $ready = CarbonImmutable::parse($started)->addDays($days);
            if (now()->greaterThanOrEqualTo($ready)) {
                $q->workflow_stage = WorkflowStage::AwaitingResults;
                $q->stage_changed_at = now();
                $q->save();
                $moved++;
            }
        }
        return $moved;
    }
}

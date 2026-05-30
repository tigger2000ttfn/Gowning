<?php

namespace App\Services;

use App\Models\Setting;

use App\Enums\QualificationStatus;
use App\Enums\QualificationType;
use App\Enums\RunResult;
use App\Models\Personnel;
use App\Models\Qualification;
use App\Models\QualificationRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Implements the MATC gowning qualification rules:
 *   - Initial qualification = 3 successful cleanroom runs (then qualified for 12 months).
 *   - Annual requalification = 1 successful run, if on or before the due date.
 *   - Lapsed (past due date) = treated as initial again, so 3 successful runs required.
 *   - Failed runs do not count toward required passes and do not reset prior passes.
 *   - A completed gowning class is the prerequisite for initial qualification runs.
 *
 * State is always derived deterministically from the run history, so the stored
 * qualification row can be rebuilt from source records at any time (validation-friendly).
 */
class QualificationEngine
{
    public const ANNUAL_VALID_MONTHS = 12;

    protected function cycleMonths(): int
    {
        return (int) Setting::get('cycle_months', self::ANNUAL_VALID_MONTHS);
    }

    protected function initialRunsRequired(): int
    {
        return (int) Setting::get('initial_runs_required', QualificationType::Initial->runsRequired());
    }

    protected function annualRunsRequired(): int
    {
        return (int) Setting::get('annual_runs_required', QualificationType::Annual->runsRequired());
    }

    protected function graceDays(): int
    {
        return (int) Setting::get('grace_days', 0);
    }

    /** Get or create the single qualification record for a person. */
    public function qualificationFor(Personnel $personnel): Qualification
    {
        return $personnel->qualification()->firstOrCreate(
            ['personnel_id' => $personnel->id],
            [
                'type' => QualificationType::Initial,
                'status' => QualificationStatus::Pending,
                'runs_required' => $this->initialRunsRequired(),
                'runs_completed' => 0,
            ],
        );
    }

    /** The gowning class prerequisite for starting initial runs. */
    public function canStartInitial(Personnel $personnel): bool
    {
        return $personnel->hasGowningClass();
    }

    /**
     * Record a run for a person and recompute their qualification from full history.
     */
    public function recordRun(Personnel $personnel, RunResult $result, array $attributes = []): QualificationRun
    {
        $qualification = $this->qualificationFor($personnel);

        $run = $qualification->runs()->create(array_merge([
            'personnel_id' => $personnel->id,
            'run_date' => $attributes['run_date'] ?? now()->toDateString(),
            'result' => $result,
            'cycle_type' => $qualification->type,
        ], $attributes, ['result' => $result]));

        $this->recompute($qualification);

        // fire automation rules for the run outcome
        \App\Services\AutomationEngine::fire(
            $result === RunResult::Pass
                ? \App\Enums\AutomationTrigger::RunPassed
                : \App\Enums\AutomationTrigger::RunFailed,
            ['personnel' => $personnel, 'qualification' => $qualification->fresh()]
        );

        return $run->fresh();
    }

    /**
     * Recompute and persist a qualification's state by replaying its runs in order.
     */
    public function recompute(Qualification $qualification): Qualification
    {
        $state = $this->replay(
            $qualification->runs()->orderBy('run_date')->orderBy('id')->get()
        );

        $qualification->fill([
            'type' => $state['type'],
            'status' => $state['status'],
            'runs_required' => $state['required'],
            'runs_completed' => $state['passes'],
            'qualified_date' => $state['qualified_date'],
            'due_date' => $state['due_date'],
        ])->save();

        // AUTOMATION: recording a run advances the GMP workflow stage. A run has been
        // performed, so move the card forward to "Run Performed" (unless it is already
        // further along in sampling/incubation/QA, or already signed off).
        $current = $qualification->workflow_stage?->value;
        $alreadyPast = in_array($current, [
            'run_performed', 'incubating', 'awaiting_results',
            'results_released', 'qa_review', 'qa_signoff',
        ], true);
        if (! $alreadyPast && $current !== 'failed') {
            $qualification->workflow_stage = \App\Enums\WorkflowStage::RunPerformed;
            $qualification->stage_changed_at = now();
            $qualification->save();
        }

        return $qualification;
    }

    /**
     * Pure state machine over a chronological run collection.
     * Returns the resulting cycle the person is currently working toward.
     *
     * @return array{type:QualificationType,status:QualificationStatus,required:int,passes:int,qualified_date:?string,due_date:?string}
     */
    public function replay(iterable $runs): array
    {
        $type = QualificationType::Initial;
        $required = $this->initialRunsRequired();
        $passes = 0;
        $status = QualificationStatus::Pending;
        $qualifiedDate = null;   // CarbonImmutable|null
        $dueDate = null;         // CarbonImmutable|null

        foreach ($runs as $run) {
            $runDate = CarbonImmutable::parse($run->run_date);

            // If already qualified but this run happens after the due date, the
            // qualification has lapsed: a fresh initial cycle (3 runs) is required.
            if ($status === QualificationStatus::Qualified
                && $dueDate !== null
                && $runDate->greaterThan($dueDate)) {
                $status = QualificationStatus::Lapsed;
                $type = QualificationType::Initial;
                $required = $this->initialRunsRequired();
                $passes = 0;
            }

            // Failed runs neither count nor reset prior passes.
            if ($run->result !== RunResult::Pass) {
                continue;
            }

            $passes++;

            if ($passes >= $required) {
                // Cycle complete: qualified, valid for 12 months from this pass.
                $qualifiedDate = $runDate;
                $dueDate = $runDate->addMonths($this->cycleMonths());
                $status = QualificationStatus::Qualified;

                // Next cycle is the annual requalification (1 run).
                $type = QualificationType::Annual;
                $required = $this->annualRunsRequired();
                $passes = 0;
            } else {
                $status = QualificationStatus::InProgress;
            }
        }

        // Present-day lapse: qualified but the due date has now passed.
        if ($status === QualificationStatus::Qualified
            && $dueDate !== null
            && $dueDate->lessThan(CarbonImmutable::now()->startOfDay())) {
            $status = QualificationStatus::Lapsed;
            $type = QualificationType::Initial;
            $required = $this->initialRunsRequired();
            $passes = 0;
        }

        return [
            'type' => $type,
            'status' => $status,
            'required' => $required,
            'passes' => $passes,
            'qualified_date' => $qualifiedDate?->toDateString(),
            'due_date' => $dueDate?->toDateString(),
        ];
    }

    /**
     * Flip qualified -> lapsed when the due date has passed (for a scheduled daily run).
     */
    public function markLapsedIfDue(Qualification $qualification): bool
    {
        if ($qualification->status === QualificationStatus::Qualified
            && $qualification->due_date !== null
            && Carbon::parse($qualification->due_date)->isPast()) {
            $this->recompute($qualification);
            return true;
        }
        return false;
    }
}

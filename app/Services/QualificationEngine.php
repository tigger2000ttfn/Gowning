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

    /** The person's active qualification cycle, creating a first one only if none exists. */
    public function qualificationFor(Personnel $personnel): Qualification
    {
        // Resolve the active (non-superseded) cycle. After a QA determination spawns a child
        // session, this returns the child, so newly recorded runs attach to the right cycle.
        $active = Qualification::currentFor($personnel->id);
        if ($active) {
            return $active;
        }

        return $personnel->qualification()->create([
            'type' => QualificationType::Initial,
            'status' => QualificationStatus::Pending,
            'runs_required' => $this->initialRunsRequired(),
            'runs_completed' => 0,
        ]);
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

        // If QA sent this person back for requalification, the first run of the new cycle
        // descends from the failed run (parent/child trace). Consume the stash once.
        $parentRunId = null;
        if (! empty($qualification->pending_parent_run_id)) {
            $parentRunId = $qualification->pending_parent_run_id;
            $qualification->pending_parent_run_id = null;
            $qualification->save();
        }

        $run = $qualification->runs()->create(array_merge([
            'personnel_id' => $personnel->id,
            'run_date' => $attributes['run_date'] ?? now()->toDateString(),
            'result' => $result,
            'cycle_type' => $qualification->type,
            'parent_run_id' => $parentRunId,
        ], $attributes, ['result' => $result]));

        $this->recompute($qualification);

        // fire automation rules only for an actual outcome (not a pending/performed run)
        if ($result === RunResult::Pass || $result === RunResult::Fail) {
            \App\Services\AutomationEngine::fire(
                $result === RunResult::Pass
                    ? \App\Enums\AutomationTrigger::RunPassed
                    : \App\Enums\AutomationTrigger::RunFailed,
                ['personnel' => $personnel, 'qualification' => $qualification->fresh()]
            );
        }

        return $run->fresh();
    }

    /**
     * Recompute and persist a qualification's state by replaying its runs in order.
     */
    public function recompute(Qualification $qualification): Qualification
    {
        // Only replay runs from the current cycle. A QA determination or lapse sets
        // cycle_started_at, so prior-cycle runs (incl. the failure) don't get recounted.
        $runsQuery = $qualification->runs()->orderBy('run_date')->orderBy('id');
        if ($qualification->cycle_started_at) {
            $runsQuery->whereDate('run_date', '>=', $qualification->cycle_started_at);
        }
        // When a determination/lapse defined this cycle (anchor set), honor its required
        // count (e.g. a custom lapsed_runs_required or QA's 1-vs-3 choice) instead of
        // always assuming the initial count.
        $startingRequired = $qualification->cycle_started_at ? (int) $qualification->runs_required : null;
        $state = $this->replay($runsQuery->get(), $startingRequired);

        // If the engine derived a qualifying date from runs, use it. Otherwise fall back to a
        // manually-entered qualified_date (seeded/historic people who have no full run history),
        // and always compute the due date as qualified_date + cycle so the Due column is correct.
        $qualifiedDate = $state['qualified_date'] ?? $qualification->qualified_date?->toDateString();
        $dueDate = $state['due_date'];
        if ($dueDate === null && $qualifiedDate) {
            $dueDate = \Illuminate\Support\Carbon::parse($qualifiedDate)
                ->addMonths($this->cycleMonths())->toDateString();
        }

        // If the person is qualified by manual entry (status says qualified) but the run replay
        // produced no qualifying date, keep the qualified status rather than downgrading it.
        $status = $state['status'];
        if ($state['qualified_date'] === null && $qualifiedDate
            && ($qualification->status instanceof \BackedEnum ? $qualification->status->value : $qualification->status) === 'qualified') {
            $status = \App\Enums\QualificationStatus::Qualified;
            // re-evaluate lapse against the computed due date
            if ($dueDate && \Illuminate\Support\Carbon::parse($dueDate)->isPast()) {
                $status = \App\Enums\QualificationStatus::Lapsed;
            }
        }

        $qualification->fill([
            'type' => $state['type'],
            'status' => $status,
            'runs_required' => $state['required'],
            'runs_completed' => $state['passes'],
            'qualified_date' => $qualifiedDate,
            'due_date' => $dueDate,
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
    public function replay(iterable $runs, ?int $startingRequired = null): array
    {
        $type = QualificationType::Initial;
        $required = $startingRequired ?? $this->initialRunsRequired();
        $passes = 0;
        $status = QualificationStatus::Pending;
        $qualifiedDate = null;   // CarbonImmutable|null
        $dueDate = null;         // CarbonImmutable|null

        foreach ($runs as $run) {
            $runDate = CarbonImmutable::parse($run->run_date);

            // Honor the run's own cycle type. If a run is recorded as an ANNUAL
            // requalification, the person's initial is assumed already done (even if that
            // historic initial was never entered): an annual pass alone qualifies them.
            // This lets a backfilled annual requal stand on its own.
            $runCycle = $run->cycle_type instanceof \BackedEnum ? $run->cycle_type->value : $run->cycle_type;
            if ($runCycle === 'annual' && $status !== QualificationStatus::Qualified) {
                $type = QualificationType::Annual;
                $required = $this->annualRunsRequired();
            }

            // If already qualified but this run happens after the due date, the
            // qualification has lapsed: a fresh initial cycle (3 runs) is required.
            if ($status === QualificationStatus::Qualified
                && $dueDate !== null
                && $runDate->greaterThan($dueDate)) {
                // A run dated after the due date: if it is an annual requal, 1 run renews;
                // otherwise treat as a fresh initial cycle.
                if ($runCycle === 'annual') {
                    $type = QualificationType::Annual;
                    $required = $this->annualRunsRequired();
                } else {
                    $status = QualificationStatus::Lapsed;
                    $type = QualificationType::Initial;
                    $required = $this->initialRunsRequired();
                }
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

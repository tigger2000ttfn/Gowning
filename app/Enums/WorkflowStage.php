<?php

namespace App\Enums;

/**
 * The full GMP gowning-qualification lifecycle. A person/run moves through these
 * stages from class completion to QA sign-off. Status-flipping between these is
 * the heart of the tracking system.
 */
enum WorkflowStage: string
{
    case ClassPending     = 'class_pending';      // needs gowning class
    case ClassComplete    = 'class_complete';     // class done, ready to schedule
    case RunScheduled     = 'run_scheduled';      // reservation approved for a slot
    case RunPerformed     = 'run_performed';      // gowned through the cleanroom
    case Incubating       = 'incubating';         // plates in incubation (LIMS), timer running
    case AwaitingResults  = 'awaiting_results';   // incubation period elapsed, plates ready to read
    case ResultsReleased  = 'results_released';   // pass/fail entered + released
    case QaReview         = 'qa_review';          // in QA approval queue
    case QaSignoff        = 'qa_signoff';         // QA signed off = Completed
    case Failed           = 'failed';             // failed run, needs QA determination

    public function label(): string
    {
        return match ($this) {
            self::ClassPending    => 'Class Pending',
            self::ClassComplete   => 'Class Complete',
            self::RunScheduled    => 'Run Scheduled',
            self::RunPerformed    => 'Run Performed',
            self::Incubating      => 'Incubating',
            self::AwaitingResults => 'Awaiting Results',
            self::ResultsReleased => 'Results Released',
            self::QaReview        => 'QA Review',
            self::QaSignoff       => 'QA Sign-off (Complete)',
            self::Failed          => 'Failed, QA Determination',
        };
    }

    /** Hex color for kanban/badges. */
    public function color(): string
    {
        return match ($this) {
            self::ClassPending    => '#9A9AA4',
            self::ClassComplete   => '#6B2C91',
            self::RunScheduled    => '#1F6FB2',
            self::RunPerformed    => '#2A7DB5',
            self::Incubating      => '#B8860B',
            self::AwaitingResults => '#C79A2E',
            self::ResultsReleased => '#0E8A6E',
            self::QaReview        => '#A4123F',
            self::QaSignoff       => '#2E7D5B',
            self::Failed          => '#C8102E',
        };
    }

    /** The ordered pipeline (Failed is off-pipeline). */
    public static function pipeline(): array
    {
        return [
            self::ClassPending, self::ClassComplete, self::RunScheduled,
            self::RunPerformed, self::Incubating, self::AwaitingResults,
            self::ResultsReleased, self::QaReview, self::QaSignoff,
        ];
    }

    /** Stages a card can move TO from this stage (forward one, plus Failed). */
    public function nextStages(): array
    {
        if ($this === self::QaSignoff) {
            return [];
        }
        if ($this === self::Failed) {
            return [self::ClassComplete, self::RunScheduled]; // retrain/requalify
        }
        $pipe = self::pipeline();
        $i = array_search($this, $pipe, true);
        $next = [];
        if ($i !== false && isset($pipe[$i + 1])) {
            $next[] = $pipe[$i + 1];
        }
        if (in_array($this, [self::RunPerformed, self::Incubating, self::AwaitingResults, self::ResultsReleased], true)) {
            $next[] = self::Failed;
        }
        return $next;
    }
}

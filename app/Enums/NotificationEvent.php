<?php

namespace App\Enums;

enum NotificationEvent: string
{
    case RunRequested    = 'run_requested';
    case RunScheduled    = 'run_scheduled';
    case RunResult       = 'run_result';
    case RunPassed       = 'run_passed';
    case RunFailed       = 'run_failed';
    case ClassCompleted  = 'class_completed';
    case Qualified       = 'qualified';
    case DueSoon         = 'due_soon';
    case Lapsed          = 'lapsed';
    case NcOpened        = 'nc_opened';
    case QaAssigned      = 'qa_assigned';
    case NewMessage      = 'new_message';
    case Announcement    = 'announcement';

    public function label(): string
    {
        return match ($this) {
            self::RunRequested => 'A run is requested (for approvers)',
            self::RunScheduled => 'My run is scheduled',
            self::RunResult    => 'My run result is recorded',
            self::RunPassed    => 'My run passes',
            self::RunFailed    => 'A run fails (for QA)',
            self::ClassCompleted => 'A class is completed (for scheduling)',
            self::Qualified    => 'A person becomes qualified',
            self::DueSoon      => 'My qualification is due soon',
            self::Lapsed       => 'A qualification lapses',
            self::NcOpened     => 'A nonconformance is opened (for QA)',
            self::QaAssigned   => 'A QA approval is assigned to me',
            self::NewMessage   => 'I receive a direct message',
            self::Announcement => 'A new announcement is posted',
        };
    }

    public function defaultInApp(): bool { return true; }
    public function defaultEmail(): bool
    {
        return in_array($this, [self::DueSoon, self::RunScheduled, self::RunResult, self::Lapsed], true);
    }

    public static function cases_(): array { return self::cases(); }
}

<?php

namespace App\Enums;

enum NotificationEvent: string
{
    case RunRequested    = 'run_requested';
    case RunScheduled    = 'run_scheduled';
    case RunResult       = 'run_result';
    case DueSoon         = 'due_soon';
    case Lapsed          = 'lapsed';
    case QaAssigned      = 'qa_assigned';
    case NewMessage      = 'new_message';
    case Announcement    = 'announcement';

    public function label(): string
    {
        return match ($this) {
            self::RunRequested => 'A run is requested (for approvers)',
            self::RunScheduled => 'My run is scheduled',
            self::RunResult    => 'My run result is recorded',
            self::DueSoon      => 'My qualification is due soon',
            self::Lapsed       => 'A qualification lapses',
            self::QaAssigned   => 'A QA approval is assigned to me',
            self::NewMessage   => 'I receive a direct message',
            self::Announcement => 'A new announcement is posted',
        };
    }

    public function defaultInApp(): bool { return true; }
    public function defaultEmail(): bool
    {
        return in_array($this, [self::DueSoon, self::RunScheduled, self::RunResult], true);
    }

    public static function cases_(): array { return self::cases(); }
}

<?php

namespace App\Enums;

enum QualificationStatus: string
{
    case Pending = 'pending';        // no successful runs yet
    case InProgress = 'in_progress'; // some passes, not enough to qualify
    case Qualified = 'qualified';    // requirement met, within due date
    case Lapsed = 'lapsed';          // past due date; must re-do initial (3 runs)

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Qualified => 'Qualified',
            self::Lapsed => 'Lapsed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'warning',
            self::Qualified => 'success',
            self::Lapsed => 'danger',
        };
    }
}

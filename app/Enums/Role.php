<?php

namespace App\Enums;

enum Role: string
{
    case SystemAdmin = 'system_admin';
    case QcMicro = 'qc_micro';
    case Qa = 'qa';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'System Admin',
            self::QcMicro => 'QC Micro Admin',
            self::Qa => 'QA / Manager',
            self::Operator => 'Trainee / Operator',
        };
    }

    /** Roles permitted to manage scheduling and record run results. */
    public function isStaff(): bool
    {
        return in_array($this, [self::SystemAdmin, self::QcMicro], true);
    }
}

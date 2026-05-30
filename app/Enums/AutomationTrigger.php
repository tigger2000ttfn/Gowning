<?php

namespace App\Enums;

enum AutomationTrigger: string
{
    case StageChanged   = 'stage_changed';
    case RunFailed      = 'run_failed';
    case RunPassed      = 'run_passed';
    case Qualified      = 'qualified';
    case Lapsed         = 'lapsed';
    case DueSoon        = 'due_soon';
    case ClassCompleted = 'class_completed';
    case NcOpened       = 'nc_opened';

    public function label(): string
    {
        return match ($this) {
            self::StageChanged   => 'A person reaches a workflow stage',
            self::RunFailed      => 'A qualification run fails',
            self::RunPassed      => 'A qualification run passes',
            self::Qualified      => 'A person becomes qualified',
            self::Lapsed         => 'A qualification lapses (overdue)',
            self::DueSoon        => 'A qualification is due soon',
            self::ClassCompleted => 'A gowning class is completed',
            self::NcOpened       => 'A non-conformance is opened',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

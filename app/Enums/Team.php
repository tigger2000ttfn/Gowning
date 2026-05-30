<?php

namespace App\Enums;

enum Team: string
{
    case Qcm = 'qcm';
    case Qa = 'qa';

    public function label(): string
    {
        return match ($this) {
            self::Qcm => 'QC Micro',
            self::Qa => 'Quality Assurance',
        };
    }

    public static function options(): array
    {
        return [self::Qcm->value => self::Qcm->label(), self::Qa->value => self::Qa->label()];
    }
}

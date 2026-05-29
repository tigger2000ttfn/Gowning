<?php

namespace App\Enums;

enum QualificationType: string
{
    case Initial = 'initial'; // 3 successful runs
    case Annual = 'annual';   // 1 successful run if on or before due date

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial Qualification',
            self::Annual => 'Annual Requalification',
        };
    }

    public function runsRequired(): int
    {
        return $this === self::Initial ? 3 : 1;
    }
}

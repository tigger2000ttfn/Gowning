<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
            self::NoShow => 'No Show',
        };
    }
}

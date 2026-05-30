<?php

namespace App\Enums;

enum RunSlotStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

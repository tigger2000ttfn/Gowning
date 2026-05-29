<?php

namespace App\Enums;

enum RunSlotStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

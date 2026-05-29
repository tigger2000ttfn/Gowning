<?php

namespace App\Enums;

enum RunResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

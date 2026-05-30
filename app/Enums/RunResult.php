<?php

namespace App\Enums;

enum RunResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Pending = 'pending';   // run performed, awaiting incubation results

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

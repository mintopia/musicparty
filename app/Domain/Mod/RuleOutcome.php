<?php

namespace App\Domain\Mod;

enum RuleOutcome: string
{
    case Accept = 'accept';
    case Hold = 'hold';
    case Reject = 'reject';

    public function severity(): int
    {
        return match ($this) {
            self::Accept => 0,
            self::Hold => 1,
            self::Reject => 2,
        };
    }
}

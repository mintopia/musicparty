<?php

namespace App\Domain\Party\Exceptions;

use App\Domain\Party\PartyState;
use RuntimeException;

class InvalidPartyTransition extends RuntimeException
{
    public static function for(PartyState $from, string $to): self
    {
        return new self("A {$from->value} Party cannot be changed to {$to}.");
    }
}

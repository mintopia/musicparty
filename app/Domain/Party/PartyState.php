<?php

namespace App\Domain\Party;

enum PartyState: string
{
    case Live = 'live';
    case Paused = 'paused';
    case Ended = 'ended';
}

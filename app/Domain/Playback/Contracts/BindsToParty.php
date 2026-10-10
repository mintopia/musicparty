<?php

namespace App\Domain\Playback\Contracts;

use App\Domain\Party\Models\Party;

interface BindsToParty
{
    public function forParty(Party $party): self;
}

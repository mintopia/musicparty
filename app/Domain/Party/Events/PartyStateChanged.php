<?php

namespace App\Domain\Party\Events;

use App\Domain\Mod\Contracts\PartyScopedEvent;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use Illuminate\Foundation\Events\Dispatchable;

class PartyStateChanged implements PartyScopedEvent
{
    use Dispatchable;

    public function __construct(
        public Party $party,
        public PartyState $old,
        public PartyState $new,
    ) {}

    public function party(): Party
    {
        return $this->party;
    }
}

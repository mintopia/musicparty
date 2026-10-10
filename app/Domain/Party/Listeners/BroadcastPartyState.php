<?php

namespace App\Domain\Party\Listeners;

use App\Domain\Party\Broadcast\PartyStateChangedEvent;
use App\Domain\Party\Events\PartyStateChanged;

readonly class BroadcastPartyState
{
    public function handle(PartyStateChanged $event): void
    {
        PartyStateChangedEvent::dispatch($event->party->code, $event->new->value);
    }
}

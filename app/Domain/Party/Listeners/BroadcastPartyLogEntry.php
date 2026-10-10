<?php

namespace App\Domain\Party\Listeners;

use App\Domain\Party\Broadcast\PartyLogEntryAddedEvent;
use App\Domain\Party\Events\PartyLogEntryRecorded;

readonly class BroadcastPartyLogEntry
{
    public function handle(PartyLogEntryRecorded $event): void
    {
        PartyLogEntryAddedEvent::dispatch($event->partyCode, $event->entryId, $event->action, $event->subject);
    }
}

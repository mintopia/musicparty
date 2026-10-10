<?php

namespace App\Domain\Party\Actions;

use App\Events\Party\PartyLogEntryAddedEvent;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\User;

readonly class RecordPartyLogEntry
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __invoke(
        Party $party,
        string $action,
        ?User $actor = null,
        ?string $subject = null,
        ?array $details = null,
        ?string $systemActor = null,
    ): PartyLogEntry {
        $entry = PartyLogEntry::query()->forceCreate([
            'party_id' => $party->id,
            'user_id' => $actor?->id,
            'system_actor' => $actor === null ? $systemActor : null,
            'action' => $action,
            'subject' => $subject,
            'details' => $details,
        ]);

        PartyLogEntryAddedEvent::dispatch($party->code, $entry->id, $action, $subject);

        return $entry;
    }
}

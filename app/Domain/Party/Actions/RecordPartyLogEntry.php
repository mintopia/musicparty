<?php

namespace App\Domain\Party\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Events\PartyLogEntryRecorded;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;

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

        PartyLogEntryRecorded::dispatch($party->code, $entry->id, $action, $subject);

        return $entry;
    }
}

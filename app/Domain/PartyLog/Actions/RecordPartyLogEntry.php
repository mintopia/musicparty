<?php

namespace App\Domain\PartyLog\Actions;

use App\Models\Party;
use App\Models\PartyLog;
use App\Models\User;

class RecordPartyLogEntry
{
    /**
     * @param  array<string, scalar|null>|null  $meta
     */
    public function handle(Party $party, ?User $actor, string $action, bool $actingAsHost = false, ?array $meta = null): PartyLog
    {
        return PartyLog::query()->create([
            'party_id' => $party->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'acting_as_host' => $actingAsHost,
            'meta' => $meta,
        ]);
    }
}

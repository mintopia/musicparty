<?php

namespace App\Domain\Stats\Actions;

use App\Domain\Party\Models\Party;
use App\Domain\Stats\Broadcast\StatsUpdatedEvent;
use App\Domain\Stats\Models\PartyStat;

readonly class RefreshPartyStats
{
    public function __construct(private ComputePartyStats $compute) {}

    public function __invoke(Party $party): PartyStat
    {
        $payload = ($this->compute)($party);

        PartyStat::query()->upsert(
            [['party_id' => $party->id, 'payload' => json_encode($payload), 'created_at' => now(), 'updated_at' => now()]],
            ['party_id'],
            ['payload', 'updated_at'],
        );

        StatsUpdatedEvent::dispatch($party->code, $payload);

        return PartyStat::query()->where('party_id', $party->id)->firstOrFail();
    }
}

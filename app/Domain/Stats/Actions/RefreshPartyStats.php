<?php

namespace App\Domain\Stats\Actions;

use App\Events\Party\StatsUpdatedEvent;
use App\Models\Party;
use App\Models\PartyStat;

readonly class RefreshPartyStats
{
    public function __construct(private ComputePartyStats $compute) {}

    public function __invoke(Party $party): PartyStat
    {
        $stat = PartyStat::query()->updateOrCreate(['party_id' => $party->id], ['payload' => ($this->compute)($party)]);

        StatsUpdatedEvent::dispatch($party->code, $stat->payload);

        return $stat;
    }
}

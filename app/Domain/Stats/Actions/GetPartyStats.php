<?php

namespace App\Domain\Stats\Actions;

use App\Models\Party;
use App\Models\PartyStat;

readonly class GetPartyStats
{
    public function __construct(private ComputePartyStats $compute) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(Party $party): array
    {
        return PartyStat::query()
            ->where('party_id', $party->id)
            ->firstOr(fn (): PartyStat => PartyStat::query()->create(['party_id' => $party->id, 'payload' => ($this->compute)($party)]))
            ->payload;
    }
}

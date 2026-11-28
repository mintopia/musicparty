<?php

namespace App\Services\Requests;

use App\Models\Party;
use App\Models\UpcomingSong;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;

class ModerationTabs
{
    public function __construct(protected PartyMods $mods) {}

    /**
     * @param  array<int, int>  $partyIds
     * @return array<int, int>
     */
    public function flaggedCounts(array $partyIds): array
    {
        return UpcomingSong::query()
            ->whereIn('party_id', $partyIds)
            ->whereNull('queued_at')
            ->whereNotNull('flagged_at')
            ->selectRaw('party_id, count(*) as total')
            ->groupBy('party_id')
            ->pluck('total', 'party_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    public function flaggedCount(Party $party): int
    {
        return $party->upcoming()->whereNull('queued_at')->whereNotNull('flagged_at')->count();
    }

    /**
     * @return array{flaggedCount: int, aiReviewEnabled: bool}
     */
    public function for(Party $party): array
    {
        return [
            'flaggedCount' => $this->flaggedCount($party),
            'aiReviewEnabled' => $this->mods->isEnabled($party, AiRequestReviewMod::CODE),
        ];
    }
}

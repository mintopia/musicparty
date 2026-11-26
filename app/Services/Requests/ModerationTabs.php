<?php

namespace App\Services\Requests;

use App\Models\Party;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;

class ModerationTabs
{
    public function __construct(protected PartyMods $mods) {}

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

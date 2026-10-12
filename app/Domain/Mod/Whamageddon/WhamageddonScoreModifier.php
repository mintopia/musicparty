<?php

namespace App\Domain\Mod\Whamageddon;

use App\Domain\Mod\Contracts\ScoreModifier;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Whamageddon\Models\WhamageddonState;
use App\Domain\Queue\Models\TrackRequest;

class WhamageddonScoreModifier implements ScoreModifier
{
    public function adjustment(ModContext $context, TrackRequest $request): int
    {
        if ($request->party_member_id !== null) {
            return 0;
        }

        return (int) WhamageddonState::query()
            ->where('party_id', $context->party->id)
            ->where('request_id', $request->id)
            ->value('accumulated_bonus');
    }
}

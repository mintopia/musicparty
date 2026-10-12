<?php

namespace App\Domain\Mod\Trust;

use App\Domain\Mod\Contracts\ScoreModifier;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Trust\Models\TrustScore;
use App\Domain\Queue\Models\TrackRequest;

class TrustScoreModifier implements ScoreModifier
{
    public function adjustment(ModContext $context, TrackRequest $request): int
    {
        $settings = new TrustSettings($context->settings);

        if (! $settings->weightingEnabled() || $request->party_member_id === null) {
            return 0;
        }

        $score = TrustScore::query()->where('party_member_id', $request->party_member_id)->value('score');

        return $score === null ? 0 : (int) round($settings->maxBonus() * (float) $score);
    }
}

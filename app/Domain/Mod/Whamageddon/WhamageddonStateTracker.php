<?php

namespace App\Domain\Mod\Whamageddon;

use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Whamageddon\Models\WhamageddonState;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\TrackRequest;

class WhamageddonStateTracker
{
    public function stateFor(Party $party): WhamageddonState
    {
        return WhamageddonState::query()->firstOrCreate(['party_id' => $party->id]);
    }

    public function adopt(Party $party, TrackRequest $request): void
    {
        $this->stateFor($party)->forceFill([
            'request_id' => $request->id,
            'accumulated_bonus' => 0,
            'last_played_at' => null,
        ])->save();
    }

    public function adoptSystemRequest(ModContext $context, TrackRequest $request): void
    {
        $settings = new WhamageddonSettings($context->settings);

        if ($request->party_member_id === null && $request->provider_track_id === $settings->trackId()) {
            $this->adopt($context->party, $request);
        }
    }

    public function clear(Party $party): void
    {
        $this->stateFor($party)->forceFill(['request_id' => null, 'accumulated_bonus' => 0, 'last_played_at' => null])->save();
    }
}

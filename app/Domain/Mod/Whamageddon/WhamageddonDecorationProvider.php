<?php

namespace App\Domain\Mod\Whamageddon;

use App\Domain\Mod\Contracts\DecorationProvider;
use App\Domain\Mod\Data\Decoration;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\DecorationAccent;
use App\Domain\Mod\Whamageddon\Models\WhamageddonState;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;

class WhamageddonDecorationProvider implements DecorationProvider
{
    public function decorate(ModContext $context, TrackRequest|Play $subject): array
    {
        if (! $subject instanceof TrackRequest) {
            return [];
        }

        $isOwn = WhamageddonState::query()
            ->where('party_id', $context->party->id)
            ->where('request_id', $subject->id)
            ->exists();

        if (! $isOwn) {
            return [];
        }

        $label = (new WhamageddonSettings($context->settings))->label();

        return [new Decoration(WhamageddonMod::ID, mb_substr($label, 0, Decoration::BADGE_MAX), null, null, DecorationAccent::Danger)];
    }
}

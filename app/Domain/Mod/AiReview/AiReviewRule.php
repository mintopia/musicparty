<?php

namespace App\Domain\Mod\AiReview;

use App\Domain\Mod\Contracts\RequestRule;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\RuleVerdict;
use App\Domain\Music\Data\TrackData;
use App\Models\PartyMember;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

readonly class AiReviewRule implements RequestRule
{
    /**
     * A misconfigured Mod throws so the Mod's failure behaviour applies.
     */
    public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict
    {
        $driver = AiReviewDriver::tryFrom((string) ($context->settings['driver'] ?? ''));

        if ($driver === null || $driver->apiKey($context) === '') {
            throw new RuntimeException('No credentials configured for the selected AI driver.');
        }

        Cache::put(AiRequestReviewMod::pendingReviewKey($context->party->id, $member->id, $track->providerTrackId), true, now()->addMinute());

        return RuleVerdict::hold('Awaiting AI review.');
    }
}

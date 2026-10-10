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
     * Holds every Request as Pending and marks it for the queued review. A misconfigured Mod throws, so its failure behaviour applies.
     */
    public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict
    {
        $driver = AiReviewDriver::tryFrom((string) ($context->settings['driver'] ?? ''));

        if ($driver === null || (string) ($context->settings[$driver->keySetting()] ?? '') === '') {
            throw new RuntimeException('No credentials configured for the selected AI driver.');
        }

        Cache::put(AiRequestReviewMod::pendingReviewKey($context->party->id, $track->providerTrackId), true, now()->addMinute());

        return RuleVerdict::hold('Awaiting AI review.');
    }
}

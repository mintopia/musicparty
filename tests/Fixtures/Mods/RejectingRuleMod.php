<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Contracts\RequestRule;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\RuleVerdict;
use App\Domain\Music\Data\TrackData;

class RejectingRuleMod extends BaseFixtureMod
{
    public function __construct(string $id = 'rejecting', private readonly string $trackId = 'track-1', private readonly string $reason = 'Not on the playlist theme')
    {
        parent::__construct($id);
    }

    public function requestRules(): array
    {
        return [new readonly class($this->trackId, $this->reason) implements RequestRule
        {
            public function __construct(private string $trackId, private string $reason) {}

            public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict
            {
                return $track->providerTrackId === $this->trackId ? RuleVerdict::reject($this->reason) : RuleVerdict::accept();
            }
        }];
    }
}

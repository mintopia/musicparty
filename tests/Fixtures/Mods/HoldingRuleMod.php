<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Mod\Contracts\RequestRule;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\RuleVerdict;
use App\Domain\Music\Data\TrackData;
use App\Models\PartyMember;

class HoldingRuleMod extends BaseFixtureMod
{
    public function __construct(string $id = 'holding', private readonly string $trackId = 'track-1')
    {
        parent::__construct($id);
    }

    public function requestRules(): array
    {
        return [new readonly class($this->trackId) implements RequestRule
        {
            public function __construct(private string $trackId) {}

            public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict
            {
                return $track->providerTrackId === $this->trackId ? RuleVerdict::hold('Needs a human look') : RuleVerdict::accept();
            }
        }];
    }
}

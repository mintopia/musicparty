<?php

namespace App\Domain\Mod\Contracts;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\RuleVerdict;
use App\Domain\Music\Data\TrackData;

interface RequestRule
{
    public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict;
}

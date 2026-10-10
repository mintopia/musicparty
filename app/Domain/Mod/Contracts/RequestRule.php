<?php

namespace App\Domain\Mod\Contracts;

use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\RuleVerdict;
use App\Domain\Music\Data\TrackData;
use App\Models\PartyMember;

interface RequestRule
{
    public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict;
}

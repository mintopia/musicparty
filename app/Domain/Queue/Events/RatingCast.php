<?php

namespace App\Domain\Queue\Events;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Contracts\PartyScopedEvent;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\VoteDirection;
use Illuminate\Foundation\Events\Dispatchable;

class RatingCast implements PartyScopedEvent
{
    use Dispatchable;

    public function __construct(
        public Party $party,
        public Play $play,
        public PartyMember $member,
        public VoteDirection $direction,
    ) {}

    public function party(): Party
    {
        return $this->party;
    }
}

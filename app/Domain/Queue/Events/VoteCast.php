<?php

namespace App\Domain\Queue\Events;

use App\Domain\Mod\Contracts\PartyScopedEvent;
use App\Domain\Queue\VoteDirection;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use Illuminate\Foundation\Events\Dispatchable;

class VoteCast implements PartyScopedEvent
{
    use Dispatchable;

    public function __construct(
        public Party $party,
        public TrackRequest $request,
        public PartyMember $member,
        public ?VoteDirection $direction,
    ) {}

    public function party(): Party
    {
        return $this->party;
    }
}

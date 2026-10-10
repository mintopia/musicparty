<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Database\Eloquent\Collection;

readonly class ListQueue
{
    public function __construct(private RankQueue $rank) {}

    /**
     * @return Collection<int, TrackRequest>
     */
    public function __invoke(Party $party, ?PartyMember $viewer = null): Collection
    {
        return ($this->rank)($party, TrackRequest::query()
            ->where('party_id', $party->id)
            ->where('status', RequestStatus::Queued)
            ->with('requester.user', 'party')
            ->with(['play' => fn ($query) => $query->withRatingSummary($viewer)])
            ->withSum(['votes as my_vote' => fn ($query) => $query->where('party_member_id', $viewer?->id)], 'value'))->requests;
    }
}

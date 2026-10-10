<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\RequestStatus;

class ListMemberVotes
{
    /**
     * @return array{votes: array<int, array{request_id: int, value: int}>, ratings: array<int, array{play_id: int, value: int}>}
     */
    public function __invoke(Party $party, PartyMember $member): array
    {
        $votes = RequestVote::query()
            ->where('party_member_id', $member->id)
            ->whereHas('request', fn ($query) => $query
                ->where('party_id', $party->id)
                ->whereIn('status', [RequestStatus::Queued, RequestStatus::UpNext, RequestStatus::Playing]))
            ->orderBy('track_request_id')
            ->get(['track_request_id', 'value'])
            ->map(fn (RequestVote $vote): array => ['request_id' => $vote->track_request_id, 'value' => $vote->value])
            ->all();

        $ratings = Rating::query()
            ->where('party_member_id', $member->id)
            ->whereHas('play', fn ($query) => $query->where('party_id', $party->id))
            ->orderBy('play_id')
            ->get(['play_id', 'value'])
            ->map(fn (Rating $rating): array => ['play_id' => $rating->play_id, 'value' => $rating->value])
            ->all();

        return ['votes' => $votes, 'ratings' => $ratings];
    }
}

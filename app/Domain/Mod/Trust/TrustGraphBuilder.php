<?php

namespace App\Domain\Mod\Trust;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use Carbon\CarbonInterface;

readonly class TrustGraphBuilder
{
    /**
     * Edge A to B is A's positive Votes on B's Requests plus A's positive Ratings on Plays of B's Requests since the cutoff.
     *
     * @return array<int, array<int, float>> voter member id => (requester member id => weight)
     */
    public function __invoke(Party $party, CarbonInterface $since): array
    {
        $votes = RequestVote::query()
            ->join('track_requests', 'track_requests.id', '=', 'request_votes.track_request_id')
            ->where('track_requests.party_id', $party->id)
            ->whereNotNull('track_requests.party_member_id')
            ->where('request_votes.value', '>', 0)
            ->where('request_votes.created_at', '>=', $since)
            ->groupBy('request_votes.party_member_id', 'track_requests.party_member_id')
            ->selectRaw('request_votes.party_member_id as voter, track_requests.party_member_id as requester, count(*) as weight')
            ->toBase()
            ->get();

        $ratings = Rating::query()
            ->join('plays', 'plays.id', '=', 'ratings.play_id')
            ->where('plays.party_id', $party->id)
            ->whereNotNull('plays.party_member_id')
            ->where('ratings.value', '>', 0)
            ->where('ratings.created_at', '>=', $since)
            ->groupBy('ratings.party_member_id', 'plays.party_member_id')
            ->selectRaw('ratings.party_member_id as voter, plays.party_member_id as requester, count(*) as weight')
            ->toBase()
            ->get();

        $memberIds = PartyMember::query()->where('party_id', $party->id)->pluck('id')->flip();
        $edges = [];

        foreach ($votes->concat($ratings) as $row) {
            $voter = (int) $row->voter;
            $requester = (int) $row->requester;

            if ($voter === $requester || ! isset($memberIds[$voter], $memberIds[$requester])) {
                continue;
            }

            $edges[$voter][$requester] = ($edges[$voter][$requester] ?? 0.0) + (float) $row->weight;
        }

        return $edges;
    }
}

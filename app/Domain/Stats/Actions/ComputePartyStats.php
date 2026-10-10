<?php

namespace App\Domain\Stats\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Support\Collection;

readonly class ComputePartyStats
{
    public const int LIMIT = 5;

    /**
     * @return array{top_tracks: array<int, array{title: string, artists: list<string>, plays: int}>, top_requesters: array<int, array{nickname: string, plays: int}>, most_upvoted: array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>, most_downvoted: array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>, upvote_leaderboard: array<int, array{nickname: string, votes: int}>, downvote_leaderboard: array<int, array{nickname: string, votes: int}>, total_time_played_ms: int}
     */
    public static function empty(): array
    {
        return ['top_tracks' => [], 'top_requesters' => [], 'most_upvoted' => [], 'most_downvoted' => [], 'upvote_leaderboard' => [], 'downvote_leaderboard' => [], 'total_time_played_ms' => 0];
    }

    /**
     * @return array{top_tracks: array<int, array{title: string, artists: list<string>, plays: int}>, top_requesters: array<int, array{nickname: string, plays: int}>, most_upvoted: array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>, most_downvoted: array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>, upvote_leaderboard: array<int, array{nickname: string, votes: int}>, downvote_leaderboard: array<int, array{nickname: string, votes: int}>, total_time_played_ms: int}
     */
    public function __invoke(Party $party): array
    {
        $plays = Play::query()
            ->where('party_id', $party->id)
            ->where(fn ($query) => $query->whereNull('track_request_id')->orWhereHas(
                'request',
                fn ($request) => $request->whereNotIn('status', [RequestStatus::Rejected, RequestStatus::Removed]),
            ))
            ->with('requester.user')
            ->get();

        $scored = $this->scoredRequests($party);

        return [
            'top_tracks' => $this->topTracks($plays),
            'top_requesters' => $this->topRequesters($plays),
            'most_upvoted' => $this->ranked($scored->filter(fn (TrackRequest $request): bool => $request->score > 0)->sortByDesc('score')),
            'most_downvoted' => $this->ranked($scored->filter(fn (TrackRequest $request): bool => $request->score < 0)->sortBy('score')),
            'upvote_leaderboard' => $this->voteLeaderboard($party, 1),
            'downvote_leaderboard' => $this->voteLeaderboard($party, -1),
            'total_time_played_ms' => (int) $plays->sum('duration_ms'),
        ];
    }

    /**
     * @param  Collection<int, Play>  $plays
     * @return array<int, array{title: string, artists: list<string>, plays: int}>
     */
    private function topTracks(Collection $plays): array
    {
        return $plays->groupBy('provider_track_id')
            ->map(function (Collection $group): array {
                $first = $group->firstOrFail();

                return [
                    'title' => $first->title,
                    'artists' => $first->artists,
                    'plays' => $group->count(),
                ];
            })
            ->sortBy([['plays', 'desc'], ['title', 'asc']])
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Play>  $plays
     * @return array<int, array{nickname: string, plays: int}>
     */
    private function topRequesters(Collection $plays): array
    {
        return $plays->whereNotNull('party_member_id')
            ->groupBy('party_member_id')
            ->map(function (Collection $group): array {
                $first = $group->firstOrFail();

                return [
                    'nickname' => $first->requester?->holder()->nickname ?? 'Unknown',
                    'plays' => $group->count(),
                ];
            })
            ->sortBy([['plays', 'desc'], ['nickname', 'asc']])
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, TrackRequest>
     */
    private function scoredRequests(Party $party): Collection
    {
        $scores = RequestVote::query()
            ->selectRaw('track_request_id, SUM(value) as score')
            ->groupBy('track_request_id')
            ->havingRaw('SUM(value) != 0')
            ->whereHas('request', fn ($request) => $request->where('party_id', $party->id)->whereNotIn('status', [
                RequestStatus::Pending, RequestStatus::Rejected, RequestStatus::Removed,
            ]))
            ->pluck('score', 'track_request_id');

        return TrackRequest::query()
            ->whereKey($scores->keys())
            ->with('requester.user')
            ->get()
            ->each(fn (TrackRequest $request) => $request->score = (int) $scores[$request->id]);
    }

    /**
     * @return array<int, array{nickname: string, votes: int}>
     */
    private function voteLeaderboard(Party $party, int $direction): array
    {
        $totals = RequestVote::query()
            ->join('track_requests', 'track_requests.id', '=', 'request_votes.track_request_id')
            ->where('track_requests.party_id', $party->id)
            ->whereNotNull('track_requests.party_member_id')
            ->whereNotIn('track_requests.status', [RequestStatus::Pending, RequestStatus::Rejected, RequestStatus::Removed])
            ->where('request_votes.value', $direction)
            ->selectRaw('track_requests.party_member_id as member_id, COUNT(*) as votes')
            ->groupBy('track_requests.party_member_id')
            ->pluck('votes', 'member_id');

        return PartyMember::query()
            ->whereKey($totals->keys())
            ->with('user')
            ->get()
            ->map(fn (PartyMember $member): array => [
                'nickname' => $member->holder()->nickname ?? 'Unknown',
                'votes' => (int) $totals[$member->id],
            ])
            ->sortBy([['votes', 'desc'], ['nickname', 'asc']])
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TrackRequest>  $requests
     * @return array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>
     */
    private function ranked(Collection $requests): array
    {
        return $requests->take(self::LIMIT)
            ->map(fn (TrackRequest $request): array => [
                'title' => $request->title,
                'artists' => $request->artists,
                'score' => (int) $request->score,
                'requested_by' => $request->requester?->holder()->nickname,
            ])
            ->values()
            ->all();
    }
}

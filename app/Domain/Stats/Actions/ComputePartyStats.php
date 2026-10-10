<?php

namespace App\Domain\Stats\Actions;

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
     * @return array{top_tracks: array<int, array{title: string, artists: list<string>, plays: int}>, top_requesters: array<int, array{nickname: string, plays: int}>, most_upvoted: array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>, most_downvoted: array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>, total_time_played_ms: int}
     */
    public static function empty(): array
    {
        return ['top_tracks' => [], 'top_requesters' => [], 'most_upvoted' => [], 'most_downvoted' => [], 'total_time_played_ms' => 0];
    }

    /**
     * @return array{top_tracks: array<int, array{title: string, artists: list<string>, plays: int}>, top_requesters: array<int, array{nickname: string, plays: int}>, most_upvoted: array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>, most_downvoted: array<int, array{title: string, artists: list<string>, score: int, requested_by: string|null}>, total_time_played_ms: int}
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

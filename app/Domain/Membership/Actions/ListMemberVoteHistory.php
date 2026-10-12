<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Queue\Models\RequestVote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class ListMemberVoteHistory
{
    public const MAX_PER_PAGE = 100;

    /**
     * @return LengthAwarePaginator<int, RequestVote>
     */
    public function __invoke(PartyMember $member, ?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        return RequestVote::query()
            ->select('request_votes.*')
            ->join('track_requests', 'track_requests.id', '=', 'request_votes.track_request_id')
            ->where('request_votes.party_member_id', $member->id)
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $search);
                $query->where(function (Builder $inner) use ($escaped): void {
                    $inner->whereRaw("track_requests.title like ? escape '!'", ["%{$escaped}%"])
                        ->orWhereRaw("track_requests.artists like ? escape '!'", ["%{$escaped}%"]);
                });
            })
            ->orderByDesc('request_votes.created_at')
            ->orderByDesc('request_votes.id')
            ->with('request')
            ->paginate(min(max($perPage, 1), self::MAX_PER_PAGE))
            ->withQueryString();
    }
}

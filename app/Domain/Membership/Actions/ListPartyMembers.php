<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class ListPartyMembers
{
    private const string ROLE_RANK_SQL = "case party_members.role when 'host' then 0 when 'moderator' then 1 when 'vip' then 2 when 'guest' then 3 else 4 end";

    public const SORTABLE = ['nickname', 'role', 'joined'];

    public const MAX_PER_PAGE = 100;

    /**
     * @return LengthAwarePaginator<int, PartyMember>
     */
    public function __invoke(
        Party $party,
        bool $includeBanned,
        ?string $search = null,
        ?PartyRole $role = null,
        string $sort = 'role',
        string $direction = 'asc',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return PartyMember::query()
            ->select('party_members.*')
            ->join('users', 'users.id', '=', 'party_members.user_id')
            ->where('party_members.party_id', $party->id)
            ->when(! $includeBanned, fn (Builder $query) => $query->where('party_members.banned', false))
            ->when($role !== null, fn (Builder $query) => $query->where('party_members.role', $role?->value))
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $search);
                $query->whereRaw("users.nickname like ? escape '!'", ["%{$escaped}%"]);
            })
            ->tap(fn (Builder $query) => $this->order($query, $sort, $direction))
            ->with('user.accounts')
            ->paginate(min(max($perPage, 1), self::MAX_PER_PAGE))
            ->withQueryString();
    }

    /**
     * @param  Builder<PartyMember>  $query
     * @param  'asc'|'desc'  $direction
     */
    private function order(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'nickname' => $query->orderBy('users.nickname', $direction),
            'joined' => $query->orderBy('party_members.created_at', $direction),
            default => $query->orderByRaw($direction === 'desc' ? self::ROLE_RANK_SQL.' desc' : self::ROLE_RANK_SQL.' asc'),
        };

        if ($sort !== 'nickname') {
            $query->orderBy('users.nickname');
        }

        $query->orderBy('party_members.id');
    }
}

<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\PartyRole;
use App\Models\Party;
use App\Models\PartyMember;
use Illuminate\Support\Collection;

readonly class ListPartyMembers
{
    /**
     * @return Collection<int, PartyMember>
     */
    public function __invoke(Party $party, bool $includeBanned): Collection
    {
        $order = array_flip(array_map(fn (PartyRole $role): string => $role->value, PartyRole::cases()));

        return PartyMember::query()
            ->whereBelongsTo($party)
            ->when(! $includeBanned, fn ($query) => $query->where('banned', false))
            ->with('user')
            ->get()
            ->sortBy(fn (PartyMember $member): string => sprintf('%d-%s', $order[$member->role->value], mb_strtolower($member->holder()->nickname)))
            ->values();
    }
}

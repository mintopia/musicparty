<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
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
            ->with('user.accounts')
            ->get()
            ->sortBy(fn (PartyMember $member): string => sprintf('%d-%s', $order[$member->role->value], mb_strtolower($member->holder()->nickname)))
            ->values();
    }
}

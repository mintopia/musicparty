<?php

namespace App\Domain\Party\Actions;

use App\Models\Party;
use App\Models\PartyLogEntry;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class ListPartyLog
{
    /**
     * @return LengthAwarePaginator<int, PartyLogEntry>
     */
    public function __invoke(Party $party, int $perPage = 50): LengthAwarePaginator
    {
        return PartyLogEntry::query()
            ->whereBelongsTo($party)
            ->with('user')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}

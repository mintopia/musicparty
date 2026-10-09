<?php

namespace App\Domain\Queue\Actions;

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Play;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPlayHistory
{
    public const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, Play>
     */
    public function __invoke(Party $party, ?PartyMember $viewer = null, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return Play::query()
            ->where('party_id', $party->id)
            ->with('requester.user')
            ->withRatingSummary($viewer)
            ->orderByDesc('played_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}

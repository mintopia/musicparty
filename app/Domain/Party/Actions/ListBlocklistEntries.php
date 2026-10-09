<?php

namespace App\Domain\Party\Actions;

use App\Models\BlocklistEntry;
use App\Models\Party;
use Illuminate\Database\Eloquent\Collection;

readonly class ListBlocklistEntries
{
    /**
     * @return Collection<int, BlocklistEntry>
     */
    public function __invoke(Party $party): Collection
    {
        return BlocklistEntry::query()
            ->whereBelongsTo($party)
            ->oldest('id')
            ->get();
    }
}

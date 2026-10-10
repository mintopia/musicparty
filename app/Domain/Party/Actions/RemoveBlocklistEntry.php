<?php

namespace App\Domain\Party\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Actions\Concerns\ManagesBlocklist;
use App\Domain\Party\Exceptions\BlocklistActionRefused;
use App\Domain\Party\FallbackPlaylistCheck;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\DB;

readonly class RemoveBlocklistEntry
{
    use ManagesBlocklist;

    public function __construct(private RecordPartyLogEntry $record, private FallbackPlaylistGate $gate) {}

    /**
     * @throws BlocklistActionRefused
     */
    public function __invoke(User $actor, Party $party, BlocklistEntry $entry): ?FallbackPlaylistCheck
    {
        return DB::transaction(function () use ($actor, $party, $entry): ?FallbackPlaylistCheck {
            $this->assertCanManage($actor, $party);

            $entry = BlocklistEntry::query()
                ->whereBelongsTo($party)
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $entry->delete();

            ($this->record)($party, 'blocklist.entry_removed', $actor, $entry->value, ['old' => $this->snapshot($entry)]);

            return $entry->is_enabled ? $this->revalidateFallbackPlaylist($actor, $party) : null;
        });
    }
}

<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Actions\Concerns\ManagesBlocklist;
use App\Domain\Party\Exceptions\BlocklistActionRefused;
use App\Domain\Party\FallbackPlaylistCheck;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Queue\BlocklistMatchType;
use App\Models\BlocklistEntry;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class UpdateBlocklistEntry
{
    use ManagesBlocklist;

    public function __construct(private RecordPartyLogEntry $record, private FallbackPlaylistGate $gate) {}

    /**
     * @return array{entry: BlocklistEntry, warning: ?FallbackPlaylistCheck}
     *
     * @throws BlocklistActionRefused
     */
    public function __invoke(
        User $actor,
        Party $party,
        BlocklistEntry $entry,
        BlocklistMatchType $type,
        string $value,
        bool $isRegex,
        bool $isEnabled,
        ?string $notes,
    ): array {
        return DB::transaction(function () use ($actor, $party, $entry, $type, $value, $isRegex, $isEnabled, $notes): array {
            $this->assertCanManage($actor, $party);
            $this->assertValidPattern($type, $isRegex, $value);

            $entry = BlocklistEntry::query()
                ->whereBelongsTo($party)
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $old = $this->snapshot($entry);

            $entry->forceFill([
                'match_type' => $type,
                'value' => $value,
                'is_regex' => $isRegex,
                'is_enabled' => $isEnabled,
                'notes' => $notes,
            ])->save();

            $new = $this->snapshot($entry);

            if ($old !== $new) {
                ($this->record)($party, 'blocklist.entry_updated', $actor, $entry->value, ['old' => $old, 'new' => $new]);
            }

            $affectsGate = array_diff_key(array_diff_assoc($new, $old), ['notes' => true]) !== [];
            $warning = $affectsGate ? $this->revalidateFallbackPlaylist($actor, $party) : null;

            return ['entry' => $entry, 'warning' => $warning];
        });
    }
}

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

readonly class AddBlocklistEntry
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
        BlocklistMatchType $type,
        string $value,
        bool $isRegex = false,
        bool $isEnabled = true,
        ?string $notes = null,
    ): array {
        return DB::transaction(function () use ($actor, $party, $type, $value, $isRegex, $isEnabled, $notes): array {
            $this->assertCanManage($actor, $party);
            $this->assertValidPattern($type, $isRegex, $value);

            $entry = (new BlocklistEntry)->forceFill([
                'match_type' => $type,
                'value' => $value,
                'is_regex' => $isRegex,
                'is_enabled' => $isEnabled,
                'notes' => $notes,
            ]);
            $entry->party()->associate($party);
            $entry->save();

            ($this->record)($party, 'blocklist.entry_added', $actor, $entry->value, ['new' => $this->snapshot($entry)]);

            $warning = $isEnabled ? $this->revalidateFallbackPlaylist($actor, $party) : null;

            return ['entry' => $entry, 'warning' => $warning];
        });
    }
}

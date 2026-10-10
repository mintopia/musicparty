<?php

namespace App\Domain\Queue;

use App\Domain\Music\Data\TrackData;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Blocklist
{
    private const int FAILURE_LOG_WINDOW_SECONDS = 60;

    /** @var array<int, array{entry: BlocklistEntry, error: string}> */
    private array $failures = [];

    public function __construct(private readonly RecordPartyLogEntry $record) {}

    /**
     * Pattern failures are recorded after the caller's transaction so a refusal's rollback cannot discard them.
     */
    public function flushFailures(): void
    {
        $failures = $this->failures;
        $this->failures = [];

        foreach ($failures as ['entry' => $entry, 'error' => $error]) {
            $party = $entry->party;

            if ($party !== null) {
                ($this->record)($party, 'blocklist.pattern_failed', subject: (string) $entry->id, details: ['error' => $error], systemActor: 'blocklist');
            }
        }
    }

    public function firstMatch(Party $party, TrackData $track): ?BlocklistEntry
    {
        return $this->firstMatchIn($this->enabledEntries($party), $track);
    }

    /**
     * @return Collection<int, BlocklistEntry>
     */
    public function enabledEntries(Party $party): Collection
    {
        return BlocklistEntry::query()
            ->whereBelongsTo($party)
            ->where('is_enabled', true)
            ->oldest('id')
            ->get();
    }

    /**
     * @param  Collection<int, BlocklistEntry>  $entries
     */
    public function firstMatchIn(Collection $entries, TrackData $track): ?BlocklistEntry
    {
        return $entries->first(fn (BlocklistEntry $entry): bool => $this->matches($entry, $track));
    }

    public function matches(BlocklistEntry $entry, TrackData $track): bool
    {
        return match ($entry->match_type) {
            BlocklistMatchType::TrackName => $this->matchesName($entry, [$track->name]),
            BlocklistMatchType::ArtistName => $this->matchesName($entry, array_map(fn ($artist): string => $artist->name, $track->artists)),
            BlocklistMatchType::AlbumName => $this->matchesName($entry, [$track->album->name]),
            BlocklistMatchType::TrackId => $entry->value === $track->providerTrackId,
            BlocklistMatchType::ArtistId => in_array($entry->value, array_map(fn ($artist): string => $artist->id, $track->artists), true),
            BlocklistMatchType::AlbumId => $entry->value === $track->album->id,
            BlocklistMatchType::Isrc => $track->isrc !== null && strcasecmp($entry->value, $track->isrc) === 0,
        };
    }

    public static function isValidPattern(string $pattern): bool
    {
        return @preg_match(self::delimit($pattern), '') !== false;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function matchesName(BlocklistEntry $entry, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            $matched = $entry->is_regex
                ? $this->regexMatches($entry, $candidate)
                : mb_strtolower($entry->value) === mb_strtolower($candidate);

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    private function regexMatches(BlocklistEntry $entry, string $candidate): bool
    {
        $result = @preg_match(self::delimit($entry->value), $candidate);

        if ($result !== false) {
            return $result === 1;
        }

        $error = preg_last_error_msg();

        Log::warning('Blocklist pattern failed to evaluate; treating the Track as blocked', [
            'blocklist_entry_id' => $entry->id,
            'error' => $error,
        ]);

        if (Cache::add("blocklist-pattern-failed:{$entry->id}", true, self::FAILURE_LOG_WINDOW_SECONDS)) {
            $this->failures[$entry->id] = ['entry' => $entry, 'error' => $error];
            app()->terminating($this->flushFailures(...));
        }

        return true;
    }

    private static function delimit(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~iu';
    }
}

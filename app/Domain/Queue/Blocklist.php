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

    private bool $deferring = false;

    /**
     * Runs a callback whose transaction may roll back, recording pattern failures afterwards so the rollback cannot discard them.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function deferringFailureRecords(callable $callback): mixed
    {
        $this->deferring = true;

        try {
            return $callback();
        } finally {
            $this->deferring = false;
            $this->recordFailures();
        }
    }

    private function recordFailures(): void
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
            BlocklistMatchType::TrackName => $this->matchesAny($entry, [$track->name], true),
            BlocklistMatchType::ArtistName => $this->matchesAny($entry, array_map(fn ($artist): string => $artist->name, $track->artists), true),
            BlocklistMatchType::AlbumName => $this->matchesAny($entry, [$track->album->name], true),
            BlocklistMatchType::TrackId => $this->matchesAny($entry, [$track->providerTrackId], false),
            BlocklistMatchType::ArtistId => $this->matchesAny($entry, array_map(fn ($artist): string => $artist->id, $track->artists), false),
            BlocklistMatchType::AlbumId => $this->matchesAny($entry, [$track->album->id], false),
            BlocklistMatchType::Isrc => $track->isrc !== null && $this->matchesAny($entry, [$track->isrc], true),
        };
    }

    public static function isValidPattern(string $pattern): bool
    {
        return @preg_match(self::delimit($pattern, true), '') !== false;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function matchesAny(BlocklistEntry $entry, array $candidates, bool $ignoreCase): bool
    {
        foreach ($candidates as $candidate) {
            $matched = $entry->is_regex
                ? $this->regexMatches($entry, $candidate, $ignoreCase)
                : preg_match(self::wildcardPattern($entry->value, $ignoreCase), $candidate) === 1;

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    private static function wildcardPattern(string $value, bool $ignoreCase): string
    {
        $literals = array_map(fn (string $part): string => preg_quote($part, '~'), explode('*', $value));

        return '~\A'.implode('.*', $literals).'\z~su'.($ignoreCase ? 'i' : '');
    }

    private function regexMatches(BlocklistEntry $entry, string $candidate, bool $ignoreCase): bool
    {
        $result = @preg_match(self::delimit($entry->value, $ignoreCase), $candidate);

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

            if (! $this->deferring) {
                $this->recordFailures();
            }
        }

        return true;
    }

    private static function delimit(string $pattern, bool $ignoreCase): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~u'.($ignoreCase ? 'i' : '');
    }
}

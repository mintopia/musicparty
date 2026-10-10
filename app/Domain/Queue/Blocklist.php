<?php

namespace App\Domain\Queue;

use App\Domain\Music\Data\TrackData;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Collection;

readonly class Blocklist
{
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
                ? @preg_match(self::delimit($entry->value), $candidate) === 1
                : mb_strtolower($entry->value) === mb_strtolower($candidate);

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    private static function delimit(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~iu';
    }
}

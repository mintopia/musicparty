<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Random\Randomizer;

class FallbackPlaylist
{
    public const CACHE_SECONDS = 3600;

    public function __construct(protected MusicProviderFactory $providers) {}

    /**
     * @return list<Track>
     */
    public function tracks(Party $party, bool $force = false): array
    {
        $cacheKey = $this->cacheKey($party);
        if (! $force) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return array_values($cached);
            }
        }

        if ($party->backup_playlist_id === null) {
            return [];
        }

        $tracks = [];
        foreach ($this->providers->forParty($party, Purpose::Search)->getPlaylistTracks($party->backup_playlist_id) as $track) {
            if (! $track->isPlayable) {
                Log::debug("{$party}: Ignoring [{$track->id}] {$track->name} because it is not playable");

                continue;
            }
            $tracks[] = $track;
        }

        Cache::put($cacheKey, $tracks, self::CACHE_SECONDS);

        return $tracks;
    }

    public function refresh(Party $party): void
    {
        $this->tracks($party, true);
    }

    public function backfill(Party $party): void
    {
        Log::debug("{$party}: Backfilling upcoming songs");
        $toAdd = Party::MINIMUM_UPCOMING - $party->upcoming()->whereNull('queued_at')->count();
        if ($toAdd <= 0) {
            return;
        }

        $tracks = $this->tracks($party);

        $existingIds = $party->upcoming()
            ->where(function ($query) {
                $query->whereNull('queued_at')
                    ->orWhere('queued_at', '>', Carbon::now()->subMinutes(30));
            })
            ->with('song')
            ->get()
            ->pluck('song.spotify_id')
            ->toArray();

        $tracks = array_values(array_filter($tracks, fn (Track $track) => ! in_array($track->id, $existingIds)));

        $toAdd = min($toAdd, count($tracks));
        $tracks = app(Randomizer::class)->shuffleArray($tracks);

        for ($i = 0; $i < $toAdd; $i++) {
            $song = Song::fromTrack($tracks[$i]);
            Log::info("{$party}: Adding {$song} from backup playlist");
            $upcoming = new UpcomingSong;
            $upcoming->party()->associate($party);
            $upcoming->song()->associate($song);
            $upcoming->save();
        }
    }

    protected function cacheKey(Party $party): string
    {
        return "party.{$party->id}.backupplaylist";
    }
}

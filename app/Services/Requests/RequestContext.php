<?php

namespace App\Services\Requests;

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Song;
use App\Services\Music\Data\Track;
use Closure;

final class RequestContext
{
    /** @var array<string, mixed> */
    private array $memo = [];

    /**
     * @param  list<Track>  $tracks
     */
    public function __construct(
        public readonly Party $party,
        public readonly PartyMember $member,
        public readonly array $tracks,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public function remember(string $key, Closure $resolve): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolve();
        }

        return $this->memo[$key];
    }

    /**
     * @return array<string, int> Song ids keyed by provider track id, for the tracks in this batch
     */
    public function knownSongIds(): array
    {
        return $this->remember('known-songs', function (): array {
            $ids = array_map(fn (Track $track): string => $track->id, $this->tracks);

            return Song::query()->where('provider_id', 'spotify')->whereIn('provider_track_id', $ids)->pluck('id', 'provider_track_id')->all();
        });
    }
}

<?php

namespace App\Services\Music\Spotify;

use App\Enums\Purpose;
use App\Exceptions\NoActiveDeviceException;
use App\Models\User;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Data\Album;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\Page;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Spotify\SpotifyGateway;
use App\Support\SpotifyPayload;
use Closure;
use Illuminate\Support\Facades\Cache;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\SpotifyWebAPIException;
use stdClass;

class SpotifyMusicProvider implements MusicProvider, ProviderPlayback
{
    private const int BROWSE_CACHE_SECONDS = 3600;

    public function __construct(
        protected SpotifyGateway $gateway,
        protected User $user,
        protected Purpose $purpose,
    ) {}

    public function id(): string
    {
        return SpotifyMapper::PROVIDER_ID;
    }

    public function searchTracks(string $query, int $page, int $perPage): Page
    {
        $result = SpotifyPayload::object($this->reader()->search($query, 'track', [
            'market' => $this->user->market,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]));
        $tracks = SpotifyPayload::object($result->tracks);

        return new Page($this->mapTracks((array) $tracks->items), (int) $tracks->total, $page, $perPage);
    }

    public function getTrack(string $id): ?Track
    {
        $payload = SpotifyPayload::object($this->reader()->getTrack($id, ['market' => $this->user->market]));

        return isset($payload->id) ? SpotifyMapper::track($payload) : null;
    }

    public function findTrack(string $id): ?Track
    {
        try {
            return $this->getTrack($id);
        } catch (SpotifyWebAPIException $exception) {
            if ($exception->getMessage() === 'invalid id') {
                return null;
            }
            throw $exception;
        }
    }

    public function getTracks(array $ids): array
    {
        $tracks = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $result = SpotifyPayload::object($this->reader()->getTracks($chunk, ['market' => $this->user->market]));
            array_push($tracks, ...$this->mapTracks((array) $result->tracks));
        }

        return $tracks;
    }

    public function getAlbum(string $id): ?Album
    {
        return Cache::remember("music.spotify.album.{$id}.{$this->user->market}", self::BROWSE_CACHE_SECONDS, function () use ($id): ?Album {
            $payload = SpotifyPayload::object($this->reader()->getAlbum($id, ['market' => $this->user->market]));

            return isset($payload->id) ? SpotifyMapper::album($payload) : null;
        });
    }

    public function getArtist(string $id): ?Artist
    {
        return Cache::remember("music.spotify.artist.{$id}", self::BROWSE_CACHE_SECONDS, function () use ($id): ?Artist {
            $payload = SpotifyPayload::object($this->reader()->getArtist($id));

            return isset($payload->id) ? SpotifyMapper::artist($payload) : null;
        });
    }

    public function getArtistTopTracks(string $id): array
    {
        $result = SpotifyPayload::object($this->reader()->getArtistTopTracks($id, ['market' => $this->user->market]));

        return $this->mapTracks((array) $result->tracks);
    }

    public function getArtistAlbums(string $id, int $page, int $perPage): Page
    {
        $result = SpotifyPayload::object($this->reader()->getArtistAlbums($id, [
            'market' => $this->user->market,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]));
        $albums = array_map(fn (mixed $album): AlbumRef => SpotifyMapper::albumRef(SpotifyPayload::object($album)), array_values((array) $result->items));

        return new Page($albums, (int) $result->total, $page, $perPage);
    }

    public function getPlaylistTracks(string $playlistId): array
    {
        $tracks = [];
        $offset = 0;
        do {
            $response = SpotifyPayload::object($this->reader()->getPlaylistTracks($playlistId, [
                'limit' => 50,
                'offset' => $offset,
                'market' => $this->user->market,
            ]));
            foreach ((array) $response->items as $item) {
                $track = SpotifyPayload::object($item)->track ?? null;
                if ($track !== null) {
                    $tracks[] = SpotifyMapper::track(SpotifyPayload::object($track));
                }
            }
            $offset += 50;
        } while ($response->next !== null);

        return $tracks;
    }

    public function appendToPlaylist(string $playlistId, array $tracks): void
    {
        $ids = array_map(fn (Track $track): string => $track->id, $tracks);
        if ($ids !== []) {
            $this->control()->addPlaylistTracks($playlistId, $ids);
        }
    }

    public function listPlaylists(): array
    {
        $playlists = [];
        $offset = 0;
        do {
            $result = SpotifyPayload::object($this->control()->getMyPlaylists(['limit' => 50, 'offset' => $offset]));
            foreach ((array) $result->items as $item) {
                $playlists[] = SpotifyMapper::playlist(SpotifyPayload::object($item));
            }
            $offset += 50;
        } while ($result->next);

        return $playlists;
    }

    public function currentPlayback(): ?PlaybackState
    {
        $playback = $this->control()->getMyCurrentPlaybackInfo(['market' => $this->user->market]);
        if ($playback === null || (array) $playback === []) {
            return null;
        }

        return SpotifyMapper::playbackState(SpotifyPayload::object($playback));
    }

    public function sendToPlayer(Track $track): void
    {
        $this->playerCommand(fn (SpotifyWebAPI $api): bool => $api->queue($track->id));
    }

    public function play(?string $deviceId = null, ?Track $track = null): void
    {
        $options = $track === null ? [] : ['uris' => ["spotify:track:{$track->id}"]];
        $this->playerCommand(fn (SpotifyWebAPI $api): bool => $api->play($deviceId ?? '', $options));
    }

    public function transferPlayback(string $deviceId): void
    {
        $this->playerCommand(fn (SpotifyWebAPI $api): bool => $api->changeMyDevice(['device_ids' => [$deviceId]]));
    }

    public function pause(): void
    {
        $this->playerCommand(fn (SpotifyWebAPI $api): bool => $api->pause());
    }

    public function next(): void
    {
        $this->playerCommand(fn (SpotifyWebAPI $api): bool => $api->next());
    }

    public function previous(): void
    {
        $this->playerCommand(fn (SpotifyWebAPI $api): bool => $api->previous());
    }

    public function seek(int $positionMs): void
    {
        $this->playerCommand(fn (SpotifyWebAPI $api): bool => $api->seek(['position_ms' => $positionMs]));
    }

    public function devices(): array
    {
        $result = SpotifyPayload::object($this->control()->getMyDevices());

        return array_map(
            fn (mixed $device): Device => SpotifyMapper::device(SpotifyPayload::object($device)),
            array_values((array) ($result->devices ?? [])),
        );
    }

    /**
     * @param  Closure(SpotifyWebAPI): bool  $command
     *
     * @throws NoActiveDeviceException
     */
    protected function playerCommand(Closure $command): void
    {
        try {
            $command($this->control());
        } catch (SpotifyWebAPIException $exception) {
            if ($exception->getCode() === 404) {
                throw new NoActiveDeviceException($exception->getMessage(), 404, $exception);
            }

            throw $exception;
        }
    }

    protected function reader(): SpotifyWebAPI
    {
        return $this->purpose === Purpose::Search
            ? $this->gateway->searchClient($this->user)
            : $this->control();
    }

    protected function control(): SpotifyWebAPI
    {
        return $this->gateway->requireClient($this->user);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<Track>
     */
    protected function mapTracks(array $items): array
    {
        $tracks = [];
        foreach ($items as $item) {
            if ($item instanceof stdClass || is_array($item)) {
                $tracks[] = SpotifyMapper::track(SpotifyPayload::object($item));
            }
        }

        return $tracks;
    }
}

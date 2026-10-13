<?php

namespace App\Services\Music\Fake;

use App\Exceptions\NoActiveDeviceException;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Data\Album;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\Page;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Data\Track;

class FakeMusicProvider implements MusicProvider, ProviderPlayback
{
    public const CATALOGUE_SIZE = 30;

    /** @var list<array{method: string, args: list<mixed>}> */
    protected array $calls = [];

    /** @var array<string, Track> */
    protected array $tracks = [];

    /** @var array<string, list<Track>> */
    protected array $playlistTracks = [];

    /** @var array<string, Playlist> */
    protected array $playlists = [];

    /** @var list<Track> */
    protected array $sentToPlayer = [];

    protected ?PlaybackState $playback = null;

    protected bool $requiresActiveDevice = false;

    protected ?string $activeDeviceId = null;

    /** @var list<Device> */
    protected array $devices = [];

    public function __construct()
    {
        for ($i = 1; $i <= self::CATALOGUE_SIZE; $i++) {
            $artist = new ArtistRef('fake-artist-'.intdiv($i - 1, 5) + 1, 'Fake Artist '.(intdiv($i - 1, 5) + 1));
            $album = new AlbumRef('fake-album-'.(intdiv($i - 1, 3) + 1), 'Fake Album '.(intdiv($i - 1, 3) + 1), "https://fake.test/albums/{$i}.jpg");
            $this->tracks["fake-track-{$i}"] = new Track('fake', "fake-track-{$i}", "Fake Track {$i}", 180000 + $i * 1000, $i % 7 === 0, true, $album, [$artist]);
        }
        $this->devices = [new Device('fake-device', 'Fake Speaker', 'Speaker', true)];
        $this->playlists['fake-playlist'] = new Playlist('fake-playlist', 'Fake Playlist', 'Fake Owner', 0);
        $this->playlistTracks['fake-playlist'] = [];
    }

    /**
     * @return list<array{method: string, args: list<mixed>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<list<mixed>>
     */
    public function callsTo(string $method): array
    {
        return array_values(array_map(
            fn (array $call): array => $call['args'],
            array_filter($this->calls, fn (array $call): bool => $call['method'] === $method),
        ));
    }

    /**
     * @return list<Track>
     */
    public function sentToPlayer(): array
    {
        return $this->sentToPlayer;
    }

    public function withoutActiveDevice(): self
    {
        $this->requiresActiveDevice = true;
        $this->activeDeviceId = null;

        return $this;
    }

    public function withTrack(Track $track): self
    {
        $this->tracks[$track->id] = $track;

        return $this;
    }

    /**
     * @param  list<Track>  $tracks
     */
    public function withPlaylist(Playlist $playlist, array $tracks = []): self
    {
        $this->playlists[$playlist->id] = $playlist;
        $this->playlistTracks[$playlist->id] = $tracks;

        return $this;
    }

    public function withPlayback(?PlaybackState $playback): self
    {
        $this->playback = $playback;

        return $this;
    }

    /**
     * @param  list<Device>  $devices
     */
    public function withDevices(array $devices): self
    {
        $this->devices = $devices;

        return $this;
    }

    public function id(): string
    {
        return 'fake';
    }

    public function displayName(): string
    {
        return 'Fake';
    }

    public function isLinked(): bool
    {
        return true;
    }

    public function needsRelink(): bool
    {
        return false;
    }

    public function trackUrl(string $trackId): ?string
    {
        return null;
    }

    public function searchTracks(string $query, int $page, int $perPage): Page
    {
        $this->record(__FUNCTION__, func_get_args());
        $needle = mb_strtolower($query);
        $matches = array_values(array_filter($this->tracks, fn (Track $track): bool => $needle === ''
            || str_contains(mb_strtolower($track->name), $needle)
            || str_contains(mb_strtolower($track->artists[0]->name ?? ''), $needle)));

        return new Page(array_slice($matches, ($page - 1) * $perPage, $perPage), count($matches), $page, $perPage);
    }

    public function getTrack(string $id): ?Track
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->tracks[$id] ?? null;
    }

    public function findTrack(string $id): ?Track
    {
        return $this->getTrack($id);
    }

    public function getTracks(array $ids): array
    {
        $this->record(__FUNCTION__, func_get_args());

        return array_values(array_filter(array_map(fn (string $id): ?Track => $this->tracks[$id] ?? null, $ids)));
    }

    public function getAlbum(string $id): ?Album
    {
        $this->record(__FUNCTION__, func_get_args());
        $tracks = array_values(array_filter($this->tracks, fn (Track $track): bool => $track->album->id === $id));
        if ($tracks === []) {
            return null;
        }

        return new Album($tracks[0]->album, $tracks[0]->artists, '2020-01-01', count($tracks), $tracks);
    }

    public function getArtist(string $id): ?Artist
    {
        $this->record(__FUNCTION__, func_get_args());
        $tracks = $this->tracksByArtist($id);

        return $tracks === [] ? null : new Artist($tracks[0]->artists[0], "https://fake.test/artists/{$id}.jpg");
    }

    public function getArtistTopTracks(string $id): array
    {
        $this->record(__FUNCTION__, func_get_args());

        return array_slice($this->tracksByArtist($id), 0, 10);
    }

    public function getArtistAlbums(string $id, int $page, int $perPage): Page
    {
        $this->record(__FUNCTION__, func_get_args());
        $albums = [];
        foreach ($this->tracksByArtist($id) as $track) {
            $albums[$track->album->id] = $track->album;
        }
        $albums = array_values($albums);

        return new Page(array_slice($albums, ($page - 1) * $perPage, $perPage), count($albums), $page, $perPage);
    }

    public function getPlaylistTracks(string $playlistId): array
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->playlistTracks[$playlistId] ?? [];
    }

    public function appendToPlaylist(string $playlistId, array $tracks): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->playlistTracks[$playlistId] = [...($this->playlistTracks[$playlistId] ?? []), ...$tracks];
    }

    public function listPlaylists(): array
    {
        $this->record(__FUNCTION__, func_get_args());

        return array_values($this->playlists);
    }

    public function currentPlayback(): ?PlaybackState
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->playback;
    }

    public function sendToPlayer(Track $track): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->assertActiveDevice();
        $this->sentToPlayer[] = $track;
    }

    public function transferPlayback(string $deviceId): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->activeDeviceId = $deviceId;
    }

    public function play(?string $deviceId = null, ?Track $track = null): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->assertActiveDevice();
    }

    public function pause(): void
    {
        $this->record(__FUNCTION__, func_get_args());
    }

    public function next(): void
    {
        $this->record(__FUNCTION__, func_get_args());
    }

    public function previous(): void
    {
        $this->record(__FUNCTION__, func_get_args());
    }

    public function seek(int $positionMs): void
    {
        $this->record(__FUNCTION__, func_get_args());
    }

    public function devices(): array
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->devices;
    }

    /**
     * @return list<Track>
     */
    protected function tracksByArtist(string $id): array
    {
        return array_values(array_filter($this->tracks, fn (Track $track): bool => $track->artists[0]->id === $id));
    }

    /**
     * @param  array<array-key, mixed>  $args
     */
    protected function record(string $method, array $args): void
    {
        $this->calls[] = ['method' => $method, 'args' => array_values($args)];
    }

    /**
     * @throws NoActiveDeviceException
     */
    protected function assertActiveDevice(): void
    {
        if ($this->requiresActiveDevice && $this->activeDeviceId === null) {
            throw new NoActiveDeviceException('Player command failed: No active device found');
        }
    }
}

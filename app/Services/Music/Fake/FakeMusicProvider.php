<?php

namespace App\Services\Music\Fake;

use App\Exceptions\NoActiveDeviceException;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Data\Album;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\AlbumSummary;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\Page;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Data\SearchOverview;
use App\Services\Music\Data\Track;
use Throwable;

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

    protected int $queueDepth = 0;

    protected ?Throwable $queueDepthFailure = null;

    /** @var list<string> */
    protected array $reservedTrackIds = [];

    protected bool $requiresActiveDevice = false;

    protected ?string $activeDeviceId = null;

    /** @var list<Device> */
    protected array $devices = [];

    /** @var array<string, Playlist> */
    protected array $ownedPlaylists = [];

    /** @var array<string, string> */
    protected array $contextNames = [];

    protected ?Throwable $playbackFailure = null;

    protected bool $relinkRequired = false;

    protected ?Throwable $devicesFailure = null;

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

    public function withQueueDepth(int $depth): self
    {
        $this->queueDepth = $depth;

        return $this;
    }

    public function failingQueueDepthWith(?Throwable $failure): self
    {
        $this->queueDepthFailure = $failure;

        return $this;
    }

    public function withReservedTrack(string $trackId): self
    {
        $this->reservedTrackIds[] = $trackId;

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

    public function withOwnedPlaylist(Playlist $playlist, int $items = 0): self
    {
        $this->ownedPlaylists[$playlist->id] = new Playlist($playlist->id, $playlist->name, $playlist->ownerName, $items);

        return $this;
    }

    /**
     * @return array<string, Playlist>
     */
    public function ownedPlaylists(): array
    {
        return $this->ownedPlaylists;
    }

    /**
     * The next player command throws this once, then behaves normally again.
     */
    public function withPlaybackFailure(?Throwable $failure): self
    {
        $this->playbackFailure = $failure;

        return $this;
    }

    public function failingDevicesWith(?Throwable $failure): self
    {
        $this->devicesFailure = $failure;

        return $this;
    }

    public function withRelinkRequired(bool $required = true): self
    {
        $this->relinkRequired = $required;

        return $this;
    }

    public function withContextName(string $uri, string $name): self
    {
        $this->contextNames[$uri] = $name;

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
        return $this->relinkRequired;
    }

    public function trackUrl(string $trackId): ?string
    {
        return null;
    }

    public function resolveTrackReference(string $reference): ?string
    {
        return preg_match('/^fake:track:([A-Za-z0-9-]+)$/D', $reference, $matches) === 1 ? $matches[1] : null;
    }

    public function isReservedTrack(string $trackId): bool
    {
        return in_array($trackId, $this->reservedTrackIds, true);
    }

    public function searchTracks(string $query, int $page, int $perPage): Page
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->pageOf($this->matchingTracks($query), $page, $perPage);
    }

    public function searchOverview(string $query, int $limit): SearchOverview
    {
        $this->record(__FUNCTION__, func_get_args());

        return new SearchOverview(
            $this->pageOf($this->matchingTracks($query), 1, $limit),
            $this->pageOf($this->matchingAlbums($query), 1, $limit),
            $this->pageOf($this->matchingArtists($query), 1, $limit),
        );
    }

    public function searchAlbums(string $query, int $page, int $perPage): Page
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->pageOf($this->matchingAlbums($query), $page, $perPage);
    }

    public function searchArtists(string $query, int $page, int $perPage): Page
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->pageOf($this->matchingArtists($query), $page, $perPage);
    }

    /**
     * @return list<Track>
     */
    protected function matchingTracks(string $query): array
    {
        $needle = mb_strtolower($query);

        return array_values(array_filter($this->tracks, fn (Track $track): bool => $needle === ''
            || str_contains(mb_strtolower($track->name), $needle)
            || str_contains(mb_strtolower($track->artists[0]->name ?? ''), $needle)));
    }

    /**
     * @return list<AlbumSummary>
     */
    protected function matchingAlbums(string $query): array
    {
        $needle = mb_strtolower($query);
        $albums = [];
        foreach ($this->tracks as $track) {
            if (isset($albums[$track->album->id]) || ($needle !== '' && ! str_contains(mb_strtolower($track->album->name), $needle))) {
                continue;
            }
            $albums[$track->album->id] = new AlbumSummary($track->album, 'Album', $track->artists, '2020');
        }

        return array_values($albums);
    }

    /**
     * @return list<Artist>
     */
    protected function matchingArtists(string $query): array
    {
        $needle = mb_strtolower($query);
        $artists = [];
        foreach ($this->tracks as $track) {
            foreach ($track->artists as $artist) {
                if (isset($artists[$artist->id]) || ($needle !== '' && ! str_contains(mb_strtolower($artist->name), $needle))) {
                    continue;
                }
                $artists[$artist->id] = new Artist($artist, "https://fake.test/artists/{$artist->id}.jpg");
            }
        }

        return array_values($artists);
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return Page<T>
     */
    protected function pageOf(array $items, int $page, int $perPage): Page
    {
        return new Page(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $page, $perPage);
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

    public function ownedPlaylist(string $playlistId): ?Playlist
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->ownedPlaylists[$playlistId] ?? null;
    }

    public function findOwnedPlaylistByName(string $name): ?Playlist
    {
        $this->record(__FUNCTION__, func_get_args());
        foreach ($this->ownedPlaylists as $playlist) {
            if ($playlist->name === $name) {
                return $playlist;
            }
        }

        return null;
    }

    public function createPrivatePlaylist(string $name, string $description): Playlist
    {
        $this->record(__FUNCTION__, func_get_args());
        $playlist = new Playlist('fake-owned-'.(count($this->ownedPlaylists) + 1), $name, 'Fake Owner', 0);
        $this->ownedPlaylists[$playlist->id] = $playlist;

        return $playlist;
    }

    public function clearPlaylist(string $playlistId): void
    {
        $this->record(__FUNCTION__, func_get_args());
        if (isset($this->ownedPlaylists[$playlistId])) {
            $playlist = $this->ownedPlaylists[$playlistId];
            $this->ownedPlaylists[$playlistId] = new Playlist($playlist->id, $playlist->name, $playlist->ownerName, 0);
        }
    }

    public function playlistContextUri(string $playlistId): string
    {
        return "fake:playlist:{$playlistId}";
    }

    public function contextName(string $contextUri): ?string
    {
        return $this->contextNames[$contextUri] ?? null;
    }

    public function currentPlayback(): ?PlaybackState
    {
        $this->record(__FUNCTION__, func_get_args());

        return $this->playback;
    }

    public function sendToPlayer(Track $track): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->failIfScheduled();
        $this->assertActiveDevice();
        $this->sentToPlayer[] = $track;
    }

    public function queueDepth(): int
    {
        $this->record(__FUNCTION__, func_get_args());
        if ($this->queueDepthFailure !== null) {
            throw $this->queueDepthFailure;
        }

        return $this->queueDepth;
    }

    public function transferPlayback(string $deviceId): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->failIfScheduled();
        $this->activeDeviceId = $deviceId;
    }

    public function play(?string $deviceId = null, ?Track $track = null): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->failIfScheduled();
        $this->assertActiveDevice();
    }

    public function playInContext(string $playlistId, Track $track, ?string $deviceId = null, int $positionMs = 0): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->failIfScheduled();
        $this->assertActiveDevice();
    }

    public function pause(): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->failIfScheduled();
    }

    public function next(): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->failIfScheduled();
    }

    public function previous(): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->failIfScheduled();
    }

    public function seek(int $positionMs): void
    {
        $this->record(__FUNCTION__, func_get_args());
        $this->failIfScheduled();
    }

    public function devices(): array
    {
        $this->record(__FUNCTION__, func_get_args());
        if ($this->devicesFailure !== null) {
            throw $this->devicesFailure;
        }

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

    protected function failIfScheduled(): void
    {
        if ($this->playbackFailure !== null) {
            $failure = $this->playbackFailure;
            $this->playbackFailure = null;

            throw $failure;
        }
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

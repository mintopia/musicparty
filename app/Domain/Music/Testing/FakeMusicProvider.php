<?php

namespace App\Domain\Music\Testing;

use App\Domain\Music\Capability;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\PlaylistData;
use App\Domain\Music\Data\SearchPage;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\UnsupportedCapability;
use Throwable;

class FakeMusicProvider implements MusicProvider
{
    private ?Throwable $nextFailure = null;

    /** @var array<string, list<string>> */
    private array $appended = [];

    /** @var list<string> */
    private array $forgotten = [];

    /** @var (callable(string): void)|null */
    private $getTrackHook;

    /**
     * @param  list<TrackData>  $tracks
     * @param  list<PlaylistData>  $playlists
     * @param  array<string, list<TrackData>>  $playlistTracks
     * @param  list<Capability>  $capabilities
     */
    public function __construct(
        private readonly array $tracks = [],
        private readonly array $playlists = [],
        private array $playlistTracks = [],
        private readonly array $capabilities = [Capability::PlaylistWrite],
        private readonly string $id = 'fake',
    ) {}

    public static function withDefaultCatalogue(): self
    {
        $album = new AlbumData('album-1', 'Test Album');
        $artist = new ArtistData('artist-1', 'Test Artist');

        $tracks = [
            new TrackData('fake', 'track-1', 'Alpha Song', [$artist], $album, 180000, false, 'ISRC0000001', ['https://example.test/cover-1.jpg']),
            new TrackData('fake', 'track-2', 'Beta Song', [$artist], $album, 200000, true, 'ISRC0000002', ['https://example.test/cover-2.jpg']),
            new TrackData('fake', 'track-3', 'Gamma Tune', [new ArtistData('artist-2', 'Other Band')], $album, 150000),
            new TrackData('fake', 'track-4', 'Region Locked', [$artist], $album, 210000, false, null, [], false),
        ];

        return new self(
            $tracks,
            [new PlaylistData('playlist-1', 'Fallback Mix', 2)],
            ['playlist-1' => [$tracks[0], $tracks[2]]],
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * @param  callable(string): void  $callback
     */
    public function onGetTrack(callable $callback): self
    {
        $this->getTrackHook = $callback;

        return $this;
    }

    public function failNextWith(Throwable $failure): self
    {
        $this->nextFailure = $failure;

        return $this;
    }

    public function rateLimitNext(int $retryAfterSeconds): self
    {
        return $this->failNextWith(new ProviderTemporaryFailure('The Music Provider is rate limiting requests.', $retryAfterSeconds));
    }

    public function search(string $query, int $limit, int $offset): SearchPage
    {
        $this->throwInjectedFailure();

        $needle = mb_strtolower($query);
        $matches = array_values(array_filter(
            $this->tracks,
            fn (TrackData $track): bool => $this->matches($track, $needle),
        ));

        $limit = max(0, $limit);
        $offset = max(0, $offset);

        return new SearchPage(array_slice($matches, $offset, $limit), count($matches), $offset, $limit);
    }

    public function getTrack(string $providerTrackId): ?TrackData
    {
        $this->throwInjectedFailure();

        if ($this->getTrackHook !== null) {
            ($this->getTrackHook)($providerTrackId);
        }

        foreach ($this->tracks as $track) {
            if ($track->providerTrackId === $providerTrackId) {
                return $track;
            }
        }

        return null;
    }

    public function playlists(string $hostAccountId): array
    {
        $this->throwInjectedFailure();

        return $this->playlists;
    }

    public function forgetPlaylist(string $playlistId): void
    {
        $this->forgotten[] = $playlistId;
    }

    /**
     * @return list<string>
     */
    public function forgottenPlaylists(): array
    {
        return $this->forgotten;
    }

    public function playlistTracks(string $playlistId, string $hostAccountId): array
    {
        $this->throwInjectedFailure();

        return $this->playlistTracks[$playlistId] ?? [];
    }

    public function supports(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function appendToPlaylist(string $playlistId, array $providerTrackIds, string $hostAccountId): void
    {
        if (! $this->supports(Capability::PlaylistWrite)) {
            throw UnsupportedCapability::for($this->id, Capability::PlaylistWrite);
        }

        $this->throwInjectedFailure();

        foreach ($providerTrackIds as $providerTrackId) {
            $this->appended[$playlistId][] = $providerTrackId;

            $track = $this->getTrack($providerTrackId);
            if ($track !== null) {
                $this->playlistTracks[$playlistId][] = $track;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function appendedTo(string $playlistId): array
    {
        return $this->appended[$playlistId] ?? [];
    }

    private function matches(TrackData $track, string $needle): bool
    {
        if (str_contains(mb_strtolower($track->name), $needle)) {
            return true;
        }

        return array_any($track->artists, fn ($artist) => str_contains(mb_strtolower($artist->name), $needle));
    }

    private function throwInjectedFailure(): void
    {
        if ($this->nextFailure === null) {
            return;
        }

        $failure = $this->nextFailure;
        $this->nextFailure = null;

        throw $failure;
    }
}

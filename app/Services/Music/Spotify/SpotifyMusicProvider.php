<?php

namespace App\Services\Music\Spotify;

use App\Enums\Purpose;
use App\Exceptions\NoActiveDeviceException;
use App\Exceptions\ProviderPremiumRequiredException;
use App\Exceptions\ProviderUnavailableException;
use App\Models\User;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Data\Album;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\AlbumSummary;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\Page;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Data\SearchOverview;
use App\Services\Music\Data\Track;
use App\Services\Spotify\BackoffRequest;
use App\Services\Spotify\SpotifyBackoffException;
use App\Services\Spotify\SpotifyGateway;
use App\Services\Spotify\SpotifyRelinkRequiredException;
use App\Support\SpotifyPayload;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\SpotifyWebAPIException;
use stdClass;

class SpotifyMusicProvider implements MusicProvider, ProviderPlayback
{
    private const int BROWSE_CACHE_SECONDS = 3600;

    public const int SEARCH_MAX_LIMIT = 10;

    public const int SEARCH_MAX_OFFSET = 1000;

    public const string CONTEXT_PLAYLIST_TRACK_ID = '62mFjcWGkt2OY9LT9EvxS2';

    private ?string $accountId = null;

    public function __construct(
        protected SpotifyGateway $gateway,
        protected User $user,
        protected Purpose $purpose,
        protected ?string $market = null,
    ) {}

    public static function make(User $user, Purpose $purpose, ?string $market = null): self
    {
        return new self(app(SpotifyGateway::class), $user, $purpose, $market);
    }

    protected function market(): string
    {
        return $this->market ?: $this->user->market;
    }

    /**
     * @return bool false when the account must be re-linked
     */
    public static function refreshAccessToken(User $user): bool
    {
        try {
            app(SpotifyGateway::class)->client($user);
        } catch (SpotifyRelinkRequiredException) {
            return false;
        }

        return true;
    }

    public function id(): string
    {
        return SpotifyMapper::PROVIDER_ID;
    }

    public function displayName(): string
    {
        return 'Spotify';
    }

    public function isLinked(): bool
    {
        return $this->user->accounts()->whereHas('provider', function ($query) {
            $query->whereIn('code', ['spotify', 'spotifysearch']);
        })->count() === 2;
    }

    public function needsRelink(): bool
    {
        return $this->user->accounts()
            ->whereNotNull('needs_relink_at')
            ->whereHas('provider', fn ($query) => $query->whereCode('spotify'))
            ->exists();
    }

    public function trackUrl(string $trackId): ?string
    {
        return "https://open.spotify.com/track/{$trackId}";
    }

    public function resolveTrackReference(string $reference): ?string
    {
        return preg_match('/^spotify:track:([A-Za-z0-9]{22})$/D', $reference, $matches) === 1 ? $matches[1] : null;
    }

    public function searchTracks(string $query, int $page, int $perPage): Page
    {
        [$page, $perPage] = $this->clampSearchPaging($page, $perPage);
        $result = $this->search($query, 'track', $perPage, ($page - 1) * $perPage);

        return $this->trackPage($result->tracks ?? null, $page, $perPage);
    }

    public function searchOverview(string $query, int $limit): SearchOverview
    {
        $limit = max(1, min($limit, self::SEARCH_MAX_LIMIT));
        $result = $this->search($query, ['track', 'album', 'artist'], $limit, 0);

        return new SearchOverview(
            $this->trackPage($result->tracks ?? null, 1, $limit),
            $this->albumPage($result->albums ?? null, 1, $limit),
            $this->artistPage($result->artists ?? null, 1, $limit),
        );
    }

    public function searchAlbums(string $query, int $page, int $perPage): Page
    {
        [$page, $perPage] = $this->clampSearchPaging($page, $perPage);
        $result = $this->search($query, 'album', $perPage, ($page - 1) * $perPage);

        return $this->albumPage($result->albums ?? null, $page, $perPage);
    }

    public function searchArtists(string $query, int $page, int $perPage): Page
    {
        [$page, $perPage] = $this->clampSearchPaging($page, $perPage);
        $result = $this->search($query, 'artist', $perPage, ($page - 1) * $perPage);

        return $this->artistPage($result->artists ?? null, $page, $perPage);
    }

    /**
     * Spotify allows at most 10 results per type and an offset of at most 1,000.
     *
     * @return array{int, int}
     */
    protected function clampSearchPaging(int $page, int $perPage): array
    {
        $perPage = max(1, min($perPage, self::SEARCH_MAX_LIMIT));

        return [max(1, min($page, intdiv(self::SEARCH_MAX_OFFSET, $perPage))), $perPage];
    }

    /**
     * @param  string|list<string>  $type
     */
    protected function search(string $query, string|array $type, int $limit, int $offset): stdClass
    {
        try {
            return SpotifyPayload::object($this->reader()->search($query, $type, [
                'market' => $this->market(),
                'limit' => $limit,
                'offset' => $offset,
            ]));
        } catch (SpotifyWebAPIException $e) {
            throw match (true) {
                $e instanceof SpotifyBackoffException => $e,
                $e->isRateLimited() => new SpotifyBackoffException(1),
                BackoffRequest::transportErrno($e) !== null => new ProviderUnavailableException(BackoffRequest::isTimeout($e), $e),
                default => $e,
            };
        }
    }

    /**
     * @return Page<Track>
     */
    protected function trackPage(mixed $payload, int $page, int $perPage): Page
    {
        $paging = SpotifyPayload::object($payload ?? (object) []);

        return new Page($this->mapTracks((array) ($paging->items ?? [])), (int) ($paging->total ?? 0), $page, $perPage);
    }

    /**
     * @return Page<AlbumSummary>
     */
    protected function albumPage(mixed $payload, int $page, int $perPage): Page
    {
        $paging = SpotifyPayload::object($payload ?? (object) []);
        $items = [];
        foreach ((array) ($paging->items ?? []) as $item) {
            if ($item instanceof stdClass || is_array($item)) {
                $items[] = SpotifyMapper::albumSummary(SpotifyPayload::object($item));
            }
        }

        return new Page($items, (int) ($paging->total ?? 0), $page, $perPage);
    }

    /**
     * @return Page<Artist>
     */
    protected function artistPage(mixed $payload, int $page, int $perPage): Page
    {
        $paging = SpotifyPayload::object($payload ?? (object) []);
        $items = [];
        foreach ((array) ($paging->items ?? []) as $item) {
            if ($item instanceof stdClass || is_array($item)) {
                $items[] = SpotifyMapper::artist(SpotifyPayload::object($item));
            }
        }

        return new Page($items, (int) ($paging->total ?? 0), $page, $perPage);
    }

    public function getTrack(string $id): ?Track
    {
        $payload = SpotifyPayload::object($this->reader()->getTrack($id, ['market' => $this->market()]));

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
            $result = SpotifyPayload::object($this->reader()->getTracks($chunk, ['market' => $this->market()]));
            array_push($tracks, ...$this->mapTracks((array) $result->tracks));
        }

        return $tracks;
    }

    public function getAlbum(string $id): ?Album
    {
        return Cache::remember("music.spotify.album.{$id}.{$this->market()}", self::BROWSE_CACHE_SECONDS, function () use ($id): ?Album {
            try {
                $payload = SpotifyPayload::object($this->reader()->getAlbum($id, ['market' => $this->market()]));
            } catch (SpotifyWebAPIException $exception) {
                if (in_array($exception->getCode(), [400, 404], true)) {
                    return null;
                }
                throw $exception;
            }

            return isset($payload->id) ? SpotifyMapper::album($payload) : null;
        });
    }

    public function getArtist(string $id): ?Artist
    {
        return Cache::remember("music.spotify.artist.{$id}", self::BROWSE_CACHE_SECONDS, function () use ($id): ?Artist {
            try {
                $payload = SpotifyPayload::object($this->reader()->getArtist($id));
            } catch (SpotifyWebAPIException $exception) {
                if (in_array($exception->getCode(), [400, 404], true)) {
                    return null;
                }
                throw $exception;
            }

            return isset($payload->id) ? SpotifyMapper::artist($payload) : null;
        });
    }

    public function getArtistTopTracks(string $id): array
    {
        return Cache::remember("music.spotify.artist.{$id}.top-tracks.{$this->market()}", self::BROWSE_CACHE_SECONDS, function () use ($id): array {
            $result = SpotifyPayload::object($this->reader()->getArtistTopTracks($id, ['market' => $this->market()]));

            return $this->mapTracks((array) $result->tracks);
        });
    }

    public function getArtistAlbums(string $id, int $page, int $perPage): Page
    {
        return Cache::remember("music.spotify.artist.{$id}.albums.{$this->market()}.{$page}.{$perPage}", self::BROWSE_CACHE_SECONDS, function () use ($id, $page, $perPage): Page {
            $result = SpotifyPayload::object($this->reader()->getArtistAlbums($id, [
                'market' => $this->market(),
                'include_groups' => 'album,single',
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
            ]));
            $albums = array_map(fn (mixed $album): AlbumRef => SpotifyMapper::albumRef(SpotifyPayload::object($album)), array_values((array) $result->items));

            return new Page($albums, (int) $result->total, $page, $perPage);
        });
    }

    public function getPlaylistTracks(string $playlistId): array
    {
        $tracks = [];
        $offset = 0;
        do {
            $response = SpotifyPayload::object($this->reader()->getPlaylistTracks($playlistId, [
                'limit' => 50,
                'offset' => $offset,
                'market' => $this->market(),
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

    public function ownedPlaylist(string $playlistId): ?Playlist
    {
        try {
            $payload = SpotifyPayload::object($this->control()->getPlaylist($playlistId, ['fields' => 'id,name,owner(id,display_name),tracks(total)']));
        } catch (SpotifyWebAPIException $exception) {
            if (in_array($exception->getCode(), [400, 403, 404], true)) {
                return null;
            }
            throw $exception;
        }
        $ownerId = isset($payload->owner) ? (string) (SpotifyPayload::object($payload->owner)->id ?? '') : '';

        return isset($payload->id) && $ownerId !== '' && $ownerId === $this->accountId() ? SpotifyMapper::playlist($payload) : null;
    }

    public function findOwnedPlaylistByName(string $name): ?Playlist
    {
        $accountId = $this->accountId();
        $offset = 0;
        do {
            $result = SpotifyPayload::object($this->control()->getMyPlaylists(['limit' => 50, 'offset' => $offset]));
            foreach ((array) $result->items as $item) {
                $item = SpotifyPayload::object($item);
                $ownerId = isset($item->owner) ? (string) (SpotifyPayload::object($item->owner)->id ?? '') : '';
                if ((string) $item->name === $name && $ownerId === $accountId) {
                    return SpotifyMapper::playlist($item);
                }
            }
            $offset += 50;
        } while ($result->next);

        return null;
    }

    public function createPrivatePlaylist(string $name, string $description): Playlist
    {
        $payload = SpotifyPayload::object($this->control()->createPlaylist($this->accountId(), ['name' => $name, 'description' => $description, 'public' => false]));

        return SpotifyMapper::playlist($payload);
    }

    public function clearPlaylist(string $playlistId): void
    {
        $this->control()->replacePlaylistTracks($playlistId, []);
    }

    public function playlistContextUri(string $playlistId): string
    {
        return "spotify:playlist:{$playlistId}";
    }

    public function contextName(string $contextUri): ?string
    {
        return Cache::remember("music.spotify.context-name.{$contextUri}", 600, function () use ($contextUri): ?string {
            if (preg_match('/^spotify:user:[^:]+:collection$/D', $contextUri) === 1) {
                return 'Liked Songs';
            }
            if (preg_match('/^spotify:(playlist|album|artist):([A-Za-z0-9]+)$/D', $contextUri, $matches) !== 1) {
                return null;
            }
            [, $kind, $id] = $matches;

            try {
                return match ($kind) {
                    'playlist' => $this->playlistName($id),
                    'album' => $this->getAlbum($id)?->ref->name,
                    default => $this->getArtist($id)?->ref->name,
                };
            } catch (SpotifyWebAPIException) {
                return null;
            }
        });
    }

    public function isReservedTrack(string $trackId): bool
    {
        return $trackId === self::CONTEXT_PLAYLIST_TRACK_ID;
    }

    public function queueDepth(): int
    {
        $queue = SpotifyPayload::object($this->control()->getMyQueue());
        $ids = collect((array) $queue->queue)->map(fn (mixed $item): mixed => SpotifyPayload::object($item)->id)->unique();

        // With nothing queued Spotify pads the queue with copies of the current context, so unique items minus the playing track is the real depth, or -1 when nothing is playing or queued.
        return $ids->count() - 1;
    }

    public function currentPlayback(): ?PlaybackState
    {
        $playback = $this->control()->getMyCurrentPlaybackInfo(['market' => $this->market()]);
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

    public function playInContext(string $playlistId, Track $track, ?string $deviceId = null, int $positionMs = 0): void
    {
        // Spotify ignores an offset into an empty context, so the track sits in the playlist only while it starts.
        $this->control()->replacePlaylistTracks($playlistId, [$track->id]);
        try {
            $this->playerCommand(fn (SpotifyWebAPI $api): bool => $api->play($deviceId ?? '', [
                'context_uri' => "spotify:playlist:{$playlistId}",
                'offset' => ['position' => 0],
                'position_ms' => $positionMs,
            ]));
        } finally {
            try {
                $this->control()->replacePlaylistTracks($playlistId, []);
            } catch (SpotifyWebAPIException $exception) {
                Log::warning("Unable to empty context playlist {$playlistId}: {$exception->getMessage()}");
            }
        }
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
            if ($exception->getCode() === 403 && $exception->getReason() === 'PREMIUM_REQUIRED') {
                throw new ProviderPremiumRequiredException($exception->getMessage(), 403, $exception);
            }

            throw $exception;
        }
    }

    protected function playlistName(string $id): ?string
    {
        $name = SpotifyPayload::object($this->control()->getPlaylist($id, ['fields' => 'name']))->name ?? null;

        return $name === null ? null : (string) $name;
    }

    protected function accountId(): string
    {
        return $this->accountId ??= (string) ($this->user->accounts()->whereHas('provider', fn ($query) => $query->whereCode('spotify'))->first()->external_id
            ?? SpotifyPayload::object($this->control()->me())->id);
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

<?php

namespace App\Domain\Music\Providers;

use App\Domain\Music\Capability;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\PlaylistData;
use App\Domain\Music\Data\SearchPage;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Providers\Spotify\SpotifyApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpotifyMusicProvider implements MusicProvider
{
    public const ID = SpotifyApi::ID;

    private const string API_URL = SpotifyApi::URL;

    private const string TOKEN_URL = 'https://accounts.spotify.com/api/token';

    private const string TOKEN_CACHE_KEY = 'music.spotify.access-token';

    private const int TOKEN_SAFETY_MARGIN_SECONDS = 60;

    private const int APPEND_CHUNK_SIZE = 100;

    private const int MAX_PAGES = 200;

    private const int PLAYLIST_CACHE_SECONDS = 300;

    public function __construct(private readonly SpotifyApi $api) {}

    public function id(): string
    {
        return self::ID;
    }

    public function search(string $query, int $limit, int $offset): SearchPage
    {
        $response = $this->get('/search', array_filter([
            'q' => $query,
            'type' => 'track',
            'limit' => $limit,
            'offset' => $offset,
            'market' => $this->api->market(),
        ], fn (mixed $value): bool => $value !== null));

        $this->api->guard($response);

        /** @var array<string, mixed> $tracks */
        $tracks = $response->json('tracks') ?? [];
        /** @var list<array<string, mixed>> $items */
        $items = array_values(array_filter($tracks['items'] ?? [], is_array(...)));

        return new SearchPage(
            array_map($this->toTrackData(...), $items),
            (int) ($tracks['total'] ?? count($items)),
            (int) ($tracks['offset'] ?? $offset),
            (int) ($tracks['limit'] ?? $limit),
        );
    }

    public function getTrack(string $providerTrackId): ?TrackData
    {
        $response = $this->get('/tracks/'.rawurlencode($providerTrackId), array_filter([
            'market' => $this->api->market(),
        ], fn (mixed $value): bool => $value !== null));

        if ($response->status() === 404) {
            return null;
        }

        $this->api->guard($response);

        /** @var array<string, mixed> $track */
        $track = $response->json();

        return $this->toTrackData($track);
    }

    public function playlists(string $hostAccountId): array
    {
        $playlists = [];

        foreach ($this->pages('/me/playlists', ['limit' => 50], $hostAccountId) as $item) {
            $tracks = $item['tracks'] ?? $item['items'] ?? [];
            $playlists[] = new PlaylistData(
                (string) $item['id'],
                (string) ($item['name'] ?? ''),
                is_array($tracks) ? (int) ($tracks['total'] ?? 0) : 0,
            );
        }

        return $playlists;
    }

    public function forgetPlaylist(string $playlistId): void
    {
        Cache::forget(self::playlistCacheKey($playlistId));
    }

    private static function playlistCacheKey(string $playlistId): string
    {
        return "music.spotify.playlist.{$playlistId}";
    }

    public function playlistTracks(string $playlistId, string $hostAccountId): array
    {
        return Cache::remember(
            self::playlistCacheKey($playlistId),
            self::PLAYLIST_CACHE_SECONDS,
            fn (): array => $this->fetchPlaylistTracks($playlistId, $hostAccountId),
        );
    }

    /**
     * @return list<TrackData>
     */
    private function fetchPlaylistTracks(string $playlistId, string $hostAccountId): array
    {
        $tracks = [];

        foreach ($this->pages('/playlists/'.rawurlencode($playlistId).'/tracks', array_filter([
            'limit' => 100,
            'market' => $this->api->market(),
        ], fn (mixed $value): bool => $value !== null), $hostAccountId) as $entry) {
            $track = $entry['track'] ?? $entry['item'] ?? null;

            if (! is_array($track) || ($entry['is_local'] ?? false) || ($track['is_local'] ?? false) || ($track['type'] ?? 'track') !== 'track' || ! isset($track['id'])) {
                continue;
            }

            $tracks[] = $this->toTrackData($track);
        }

        return $tracks;
    }

    public function supports(Capability $capability): bool
    {
        return match ($capability) {
            Capability::PlaylistWrite => true,
        };
    }

    public function appendToPlaylist(string $playlistId, array $providerTrackIds, string $hostAccountId): void
    {
        $uris = array_map(fn (string $id): string => "spotify:track:{$id}", $providerTrackIds);

        foreach (array_chunk($uris, self::APPEND_CHUNK_SIZE) as $chunk) {
            $response = $this->api->userRequest(
                fn (PendingRequest $request): Response => $request->asJson()->post(self::API_URL.'/playlists/'.rawurlencode($playlistId).'/tracks', ['uris' => $chunk]),
                $hostAccountId,
            );

            $this->api->guard($response);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function pages(string $path, array $query, string $hostAccountId): array
    {
        $items = [];
        $url = self::API_URL.$path;

        for ($page = 0; $url !== null && $page < self::MAX_PAGES; $page++) {
            $response = $this->api->userRequest(
                fn (PendingRequest $request): Response => $request->get($url, $page === 0 ? $query : []),
                $hostAccountId,
            );

            $this->api->guard($response);

            $items = [...$items, ...array_values(array_filter($response->json('items') ?? [], is_array(...)))];
            $next = $response->json('next');
            $url = is_string($next) && $next !== '' ? $next : null;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query): Response
    {
        $this->api->assertConfigured();
        $this->api->assertNotBackingOff();

        try {
            $response = $this->send($path, $query, $this->accessToken());

            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
                $response = $this->send($path, $query, $this->accessToken());
            }
        } catch (ConnectionException) {
            throw new ProviderTemporaryFailure('Spotify could not be reached.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function send(string $path, array $query, string $token): Response
    {
        return Http::withToken($token)
            ->acceptJson()
            ->get(self::API_URL.$path, $query);
    }

    private function accessToken(): string
    {
        /** @var string|null $cached */
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached)) {
            return $cached;
        }

        /** @var PendingRequest $request */
        $request = Http::withBasicAuth($this->api->clientId() ?? '', $this->api->clientSecret() ?? '')->asForm();
        $response = $request->post(self::TOKEN_URL, ['grant_type' => 'client_credentials']);

        if ($response->status() === 429) {
            throw $this->api->startBackoff($response);
        }

        if ($response->serverError()) {
            throw new ProviderTemporaryFailure('Spotify token endpoint failed.');
        }

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            throw ProviderUnavailableException::forProvider(self::ID);
        }

        $ttl = max(1, (int) $response->json('expires_in', 3600) - self::TOKEN_SAFETY_MARGIN_SECONDS);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $track
     */
    private function toTrackData(array $track): TrackData
    {
        /** @var list<array<string, mixed>> $artists */
        $artists = $track['artists'] ?? [];
        /** @var array<string, mixed> $album */
        $album = $track['album'] ?? [];
        /** @var list<array<string, mixed>> $images */
        $images = $album['images'] ?? [];
        /** @var array<string, mixed> $externalIds */
        $externalIds = $track['external_ids'] ?? [];
        $isrc = $externalIds['isrc'] ?? null;

        return new TrackData(
            self::ID,
            (string) $track['id'],
            (string) ($track['name'] ?? ''),
            array_map(fn (array $artist): ArtistData => new ArtistData((string) $artist['id'], (string) ($artist['name'] ?? '')), $artists),
            new AlbumData((string) ($album['id'] ?? ''), (string) ($album['name'] ?? '')),
            (int) ($track['duration_ms'] ?? 0),
            (bool) ($track['explicit'] ?? false),
            is_string($isrc) && $isrc !== '' ? $isrc : null,
            array_values(array_map(fn (array $image): string => (string) $image['url'], array_filter($images, fn (array $image): bool => isset($image['url'])))),
            ($track['is_playable'] ?? true) !== false,
        );
    }
}

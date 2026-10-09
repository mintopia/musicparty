<?php

namespace App\Domain\Music\Providers;

use App\Domain\Music\Capability;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\SearchPage;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Exceptions\UnsupportedCapability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpotifyMusicProvider implements MusicProvider
{
    public const ID = 'spotify';

    private const string API_URL = 'https://api.spotify.com/v1';

    private const string TOKEN_URL = 'https://accounts.spotify.com/api/token';

    private const string TOKEN_CACHE_KEY = 'music.spotify.access-token';

    private const string BACKOFF_CACHE_KEY = 'music.spotify.backoff-until';

    private const int TOKEN_SAFETY_MARGIN_SECONDS = 60;

    private const int DEFAULT_RETRY_AFTER_SECONDS = 30;

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
            'market' => $this->market(),
        ], fn (mixed $value): bool => $value !== null));

        $this->guard($response);

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
            'market' => $this->market(),
        ], fn (mixed $value): bool => $value !== null));

        if ($response->status() === 404) {
            return null;
        }

        $this->guard($response);

        /** @var array<string, mixed> $track */
        $track = $response->json();

        return $this->toTrackData($track);
    }

    public function playlists(string $hostAccountId): array
    {
        throw UnsupportedCapability::for(self::ID, Capability::PlaylistWrite);
    }

    public function playlistTracks(string $playlistId): array
    {
        throw UnsupportedCapability::for(self::ID, Capability::PlaylistWrite);
    }

    public function supports(Capability $capability): bool
    {
        return false;
    }

    public function appendToPlaylist(string $playlistId, array $providerTrackIds): void
    {
        throw UnsupportedCapability::for(self::ID, Capability::PlaylistWrite);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query): Response
    {
        $this->assertConfigured();
        $this->assertNotBackingOff();

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

    private function guard(Response $response): void
    {
        $status = $response->status();

        if ($status === 429) {
            throw $this->startBackoff($response);
        }

        if ($status === 401 || $status === 403) {
            throw ProviderUnavailableException::forProvider(self::ID);
        }

        if ($status >= 400) {
            throw new ProviderTemporaryFailure("Spotify responded with status {$status}.");
        }
    }

    private function accessToken(): string
    {
        /** @var string|null $cached */
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached)) {
            return $cached;
        }

        /** @var PendingRequest $request */
        $request = Http::withBasicAuth($this->clientId() ?? '', $this->clientSecret() ?? '')->asForm();
        $response = $request->post(self::TOKEN_URL, ['grant_type' => 'client_credentials']);

        if ($response->status() === 429) {
            throw $this->startBackoff($response);
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

    private function startBackoff(Response $response): ProviderTemporaryFailure
    {
        $header = $response->header('Retry-After');
        $retryAfter = is_numeric($header) ? max(1, (int) $header) : self::DEFAULT_RETRY_AFTER_SECONDS;

        Cache::put(self::BACKOFF_CACHE_KEY, now()->getTimestamp() + $retryAfter, $retryAfter);

        return new ProviderTemporaryFailure('Spotify is rate limiting requests.', $retryAfter);
    }

    private function assertNotBackingOff(): void
    {
        $until = Cache::get(self::BACKOFF_CACHE_KEY);

        if (! is_int($until)) {
            return;
        }

        $remaining = $until - now()->getTimestamp();

        if ($remaining > 0) {
            throw new ProviderTemporaryFailure('Spotify is rate limiting requests.', $remaining);
        }
    }

    private function assertConfigured(): void
    {
        if ($this->clientId() === null || $this->clientSecret() === null) {
            throw ProviderUnavailableException::forProvider(self::ID);
        }
    }

    private function clientId(): ?string
    {
        return $this->configured('client_id');
    }

    private function clientSecret(): ?string
    {
        return $this->configured('client_secret');
    }

    private function market(): ?string
    {
        return $this->configured('market');
    }

    private function configured(string $key): ?string
    {
        $value = config("services.spotify.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
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

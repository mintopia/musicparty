<?php

namespace Tests\Support;

use SpotifyWebAPI\SpotifyWebAPI;

class FakeSpotify extends SpotifyWebAPI
{
    /** @var array<int, array{method: string, args: array<int, mixed>}> */
    public array $calls = [];

    /** @var array<string, mixed> */
    protected array $responses = [];

    public static function install(): self
    {
        $fake = new self;
        app()->bind(SpotifyWebAPI::class, fn () => $fake);

        return $fake;
    }

    public function respond(string $method, mixed $response): self
    {
        $this->responses[$method] = $response;

        return $this;
    }

    /**
     * @param  array<int, string>  $spotifyIds
     */
    public function withBackupPlaylist(array $spotifyIds): self
    {
        $items = array_map(fn (string $id) => (object) [
            'added_at' => '2024-01-01T00:00:00Z',
            'is_local' => false,
            'track' => self::track($id),
        ], $spotifyIds);

        return $this->respond('getPlaylistTracks', (object) [
            'href' => 'https://api.spotify.com/v1/playlists/backup/tracks',
            'items' => $items,
            'limit' => 50,
            'next' => null,
            'offset' => 0,
            'previous' => null,
            'total' => count($items),
        ]);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function callsTo(string $method): array
    {
        return array_values(array_map(
            fn (array $call) => $call['args'],
            array_filter($this->calls, fn (array $call) => $call['method'] === $method)
        ));
    }

    public static function track(string $id = 'track1', string $name = 'Test Track'): object
    {
        return (object) [
            'id' => $id,
            'type' => 'track',
            'uri' => "spotify:track:{$id}",
            'name' => $name,
            'duration_ms' => 200000,
            'explicit' => false,
            'is_local' => false,
            'is_playable' => true,
            'popularity' => 50,
            'album' => (object) [
                'id' => "album-{$id}",
                'name' => 'Test Album',
                'type' => 'album',
                'images' => [
                    (object) ['url' => "https://i.scdn.co/image/{$id}", 'height' => 640, 'width' => 640],
                ],
                'artists' => [self::artist()],
            ],
            'artists' => [self::artist()],
        ];
    }

    public static function artist(string $id = 'artist1', string $name = 'Test Artist'): object
    {
        return (object) ['id' => $id, 'type' => 'artist', 'name' => $name, 'uri' => "spotify:artist:{$id}"];
    }

    public static function device(string $id = 'device1'): object
    {
        return (object) [
            'id' => $id,
            'is_active' => true,
            'is_private_session' => false,
            'is_restricted' => false,
            'name' => 'Test Speaker',
            'type' => 'Speaker',
            'volume_percent' => 50,
        ];
    }

    public static function playback(?object $track = null, bool $playing = true, int $progressMs = 1000): object
    {
        return (object) [
            'device' => self::device(),
            'is_playing' => $playing,
            'progress_ms' => $progressMs,
            'item' => $track,
            'currently_playing_type' => 'track',
            'repeat_state' => 'off',
            'shuffle_state' => false,
        ];
    }

    public function getMyCurrentPlaybackInfo(array|object $options = []): array|object|null
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function addPlaylistTracks(string $playlistId, string|array $tracks, array|object $options = []): string|bool
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function getMyQueue()
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function getMyDevices(): array|object
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function getMyPlaylists(array|object $options = []): array|object
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function getMyRecentTracks(array|object $options = []): array|object
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function getPlaylist(string $playlistId, array|object $options = []): array|object
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function getPlaylistTracks(string $playlistId, array|object $options = []): array|object
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function getTrack(string $trackId, array|object $options = []): array|object
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function search(string $query, string|array $type, array|object $options = []): array|object
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function next(string $deviceId = ''): bool
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function pause(string $deviceId = ''): bool
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function play(string $deviceId = '', array|object $options = []): bool
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function previous(string $deviceId = ''): bool
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function queue(string $trackUri, string $deviceId = ''): bool
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function seek(array|object $options): bool
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    protected function record(string $method, array $args): mixed
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        return $this->responses[$method] ?? $this->defaultResponse($method);
    }

    protected function defaultResponse(string $method): mixed
    {
        return match ($method) {
            'getMyCurrentPlaybackInfo' => null,
            'getMyQueue' => (object) ['currently_playing' => null, 'queue' => []],
            'getMyDevices' => (object) ['devices' => [self::device()]],
            'getMyPlaylists', 'getMyRecentTracks' => (object) ['items' => [], 'total' => 0, 'limit' => 50, 'offset' => 0, 'next' => null],
            'getPlaylistTracks' => (object) ['items' => [], 'total' => 0, 'limit' => 50, 'offset' => 0, 'next' => null],
            'search' => (object) ['tracks' => (object) ['items' => [], 'total' => 0, 'limit' => 20, 'offset' => 0]],
            'getTrack' => self::track(),
            default => true,
        };
    }
}

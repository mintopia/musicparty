<?php

namespace App\Domain\Playback\Spotify;

use App\Domain\Music\Providers\Spotify\SpotifyApi;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\Data\PlaybackDevice;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\Exceptions\PlaybackDeviceNotFoundException;
use App\Domain\Playback\Exceptions\PlayerCommandRejectedException;
use App\Domain\Playback\PlaybackStatus;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class SpotifyPlaybackClient implements PlaybackClient
{
    public function __construct(private readonly SpotifyApi $api) {}

    public function currentPlayback(string $hostAccountId): PlaybackState
    {
        $response = $this->api->userRequest(
            fn (PendingRequest $request): Response => $request->get(SpotifyApi::URL.'/me/player', array_filter([
                'market' => $this->api->market(),
                'additional_types' => 'track',
            ], fn (mixed $value): bool => $value !== null)),
            $hostAccountId,
        );

        if ($response->status() === 204 || $response->status() === 202) {
            return PlaybackState::stopped();
        }

        $this->api->guard($response);

        $item = $response->json('item');

        if (! is_array($item) || ($item['type'] ?? 'track') !== 'track' || ! isset($item['id'])) {
            return PlaybackState::stopped();
        }

        $linkedFrom = $item['linked_from'] ?? null;
        $trackId = is_array($linkedFrom) && isset($linkedFrom['id']) ? (string) $linkedFrom['id'] : (string) $item['id'];

        return new PlaybackState(
            $response->json('is_playing') === true ? PlaybackStatus::Playing : PlaybackStatus::Paused,
            new TrackReference(SpotifyApi::ID, $trackId),
            (int) $response->json('progress_ms', 0),
            CarbonImmutable::now(),
            isset($item['duration_ms']) ? (int) $item['duration_ms'] : null,
        );
    }

    public function queueTrack(string $providerTrackId, string $hostAccountId, ?string $deviceId = null): void
    {
        $url = SpotifyApi::URL.'/me/player/queue?'.$this->query(['uri' => "spotify:track:{$providerTrackId}"], $deviceId);

        $response = $this->api->userRequest(
            fn (PendingRequest $request): Response => $request->post($url),
            $hostAccountId,
        );

        $this->api->guard($response);
    }

    public function play(string $hostAccountId, ?string $deviceId = null): void
    {
        $url = SpotifyApi::URL.'/me/player/play'.$this->suffix([], $deviceId);
        $this->command(fn (PendingRequest $request): Response => $request->put($url), $hostAccountId, $deviceId);
    }

    public function pause(string $hostAccountId, ?string $deviceId = null): void
    {
        $url = SpotifyApi::URL.'/me/player/pause'.$this->suffix([], $deviceId);
        $this->command(fn (PendingRequest $request): Response => $request->put($url), $hostAccountId, $deviceId);
    }

    public function next(string $hostAccountId, ?string $deviceId = null): void
    {
        $url = SpotifyApi::URL.'/me/player/next'.$this->suffix([], $deviceId);
        $this->command(fn (PendingRequest $request): Response => $request->post($url), $hostAccountId, $deviceId);
    }

    public function previous(string $hostAccountId, ?string $deviceId = null): void
    {
        $url = SpotifyApi::URL.'/me/player/previous'.$this->suffix([], $deviceId);
        $this->command(fn (PendingRequest $request): Response => $request->post($url), $hostAccountId, $deviceId);
    }

    public function seek(int $positionMs, string $hostAccountId, ?string $deviceId = null): void
    {
        $url = SpotifyApi::URL.'/me/player/seek'.$this->suffix(['position_ms' => $positionMs], $deviceId);
        $this->command(fn (PendingRequest $request): Response => $request->put($url), $hostAccountId, $deviceId);
    }

    public function volume(int $percent, string $hostAccountId, ?string $deviceId = null): void
    {
        $url = SpotifyApi::URL.'/me/player/volume'.$this->suffix(['volume_percent' => $percent], $deviceId);
        $this->command(fn (PendingRequest $request): Response => $request->put($url), $hostAccountId, $deviceId);
    }

    public function devices(string $hostAccountId): array
    {
        $response = $this->api->userRequest(
            fn (PendingRequest $request): Response => $request->get(SpotifyApi::URL.'/me/player/devices'),
            $hostAccountId,
        );

        $this->api->guard($response);

        $devices = [];

        foreach ((array) $response->json('devices', []) as $device) {
            if (! is_array($device) || ! isset($device['id'], $device['name'])) {
                continue;
            }

            $devices[] = new PlaybackDevice(
                (string) $device['id'],
                (string) $device['name'],
                (string) ($device['type'] ?? 'Unknown'),
                ($device['is_active'] ?? false) === true,
            );
        }

        return $devices;
    }

    /**
     * @param  Closure(PendingRequest): Response  $send
     */
    private function command(Closure $send, string $hostAccountId, ?string $deviceId = null): void
    {
        $response = $this->api->userRequest($send, $hostAccountId);
        $reason = $response->json('error.reason');

        if ($deviceId !== null && $response->status() === 404 && $reason !== 'NO_ACTIVE_DEVICE') {
            throw new PlaybackDeviceNotFoundException($deviceId);
        }

        if ($response->status() === 404 || $reason === 'NO_ACTIVE_DEVICE') {
            throw new PlayerCommandRejectedException('Spotify has no active device for the Host. Start playing on a Spotify device and try again.');
        }

        if ($response->status() === 403 && is_string($reason)) {
            $message = $response->json('error.message');

            throw new PlayerCommandRejectedException('Spotify refused the command: '.(is_string($message) && $message !== '' ? $message : $reason));
        }

        $this->api->guard($response);
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private function suffix(array $query, ?string $deviceId): string
    {
        $built = $this->query($query, $deviceId);

        return $built === '' ? '' : "?{$built}";
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private function query(array $query, ?string $deviceId): string
    {
        if ($deviceId !== null) {
            $query['device_id'] = $deviceId;
        }

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}

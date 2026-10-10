<?php

namespace App\Domain\Playback\Spotify;

use App\Domain\Music\Providers\Spotify\SpotifyApi;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
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

    public function queueTrack(string $providerTrackId, string $hostAccountId): void
    {
        $uri = rawurlencode("spotify:track:{$providerTrackId}");

        $response = $this->api->userRequest(
            fn (PendingRequest $request): Response => $request->post(SpotifyApi::URL."/me/player/queue?uri={$uri}"),
            $hostAccountId,
        );

        $this->api->guard($response);
    }

    public function play(string $hostAccountId): void
    {
        $this->command(fn (PendingRequest $request): Response => $request->put(SpotifyApi::URL.'/me/player/play'), $hostAccountId);
    }

    public function pause(string $hostAccountId): void
    {
        $this->command(fn (PendingRequest $request): Response => $request->put(SpotifyApi::URL.'/me/player/pause'), $hostAccountId);
    }

    public function next(string $hostAccountId): void
    {
        $this->command(fn (PendingRequest $request): Response => $request->post(SpotifyApi::URL.'/me/player/next'), $hostAccountId);
    }

    public function seek(int $positionMs, string $hostAccountId): void
    {
        $this->command(fn (PendingRequest $request): Response => $request->put(SpotifyApi::URL."/me/player/seek?position_ms={$positionMs}"), $hostAccountId);
    }

    public function volume(int $percent, string $hostAccountId): void
    {
        $this->command(fn (PendingRequest $request): Response => $request->put(SpotifyApi::URL."/me/player/volume?volume_percent={$percent}"), $hostAccountId);
    }

    /**
     * @param  Closure(PendingRequest): Response  $send
     */
    private function command(Closure $send, string $hostAccountId): void
    {
        $response = $this->api->userRequest($send, $hostAccountId);
        $reason = $response->json('error.reason');

        if ($response->status() === 404 || $reason === 'NO_ACTIVE_DEVICE') {
            throw new PlayerCommandRejectedException('Spotify has no active device for the Host. Start playing on a Spotify device and try again.');
        }

        if ($response->status() === 403 && is_string($reason)) {
            $message = $response->json('error.message');

            throw new PlayerCommandRejectedException('Spotify refused the command: '.(is_string($message) && $message !== '' ? $message : $reason));
        }

        $this->api->guard($response);
    }
}

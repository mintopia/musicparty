<?php

namespace App\Domain\Playback\Spotify;

use App\Domain\Music\Providers\Spotify\SpotifyApi;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\PlaybackStatus;
use Carbon\CarbonImmutable;
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
}

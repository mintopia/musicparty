<?php

namespace App\Services\Playback;

use App\Enums\Purpose;
use App\Models\Party;
use App\Services\Contracts\MusicProvider;
use App\Services\Music\Data\PlaybackConnectionStatus;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\MusicProviderFactory;
use App\Services\PlaybackStatus;
use Carbon\CarbonImmutable;

class EncoreStateMapper implements PushedStateMapper
{
    public function __construct(
        private readonly MusicProviderFactory $providers,
        private readonly PlaybackStatus $status,
    ) {}

    public function channelPrefix(): string
    {
        return 'private-encore.';
    }

    public function map(Party $party, string $event, array $data): ?PlaybackState
    {
        $type = $data['type'] ?? (str_starts_with($event, 'client-') ? substr($event, 7) : $event);
        $previous = $this->status->recall($party) ?? PlaybackState::idle(PlaybackSource::Encore);
        $provider = fn (): MusicProvider => $this->providers->forParty($party, Purpose::Control);

        return match ($type) {
            'playback_state' => $this->playbackState($provider, $previous, $data),
            'playback_changed' => $this->playbackChanged($previous, $data),
            'track_changed' => $this->trackChanged($provider, $previous, $data),
            'position_sync' => $this->positionSync($previous, $data),
            'queue_changed' => $this->queueChanged($provider, $previous, $data),
            'auth_state' => is_bool($data['logged_in'] ?? null)
                ? $this->with($previous, connectionStatus: $data['logged_in'] ? PlaybackConnectionStatus::Connected : PlaybackConnectionStatus::LoggedOut)
                : null,
            'error' => $this->with($previous, connectionStatus: PlaybackConnectionStatus::Error),
            default => null,
        };
    }

    /**
     * @param  callable(): MusicProvider  $provider
     * @param  array<string, mixed>  $data
     */
    private function playbackState(callable $provider, PlaybackState $previous, array $data): ?PlaybackState
    {
        $isPlaying = $this->isPlaying($data['status'] ?? null);
        $position = $this->position($data);
        if ($isPlaying === null || $position === false) {
            return null;
        }

        $item = null;
        if (($data['item'] ?? null) !== null) {
            $item = $this->item($provider, $data['item']);
            if ($item === null) {
                return null;
            }
        }

        $connection = ($data['logged_in'] ?? true) === false ? PlaybackConnectionStatus::LoggedOut : PlaybackConnectionStatus::Connected;

        return $this->with(
            $previous,
            isPlaying: $isPlaying,
            trackId: $item['trackId'] ?? null,
            durationMs: $item['durationMs'] ?? null,
            progressMs: $position['progressMs'] ?? 0,
            sampledAt: $position['sampledAt'] ?? CarbonImmutable::now(),
            connectionStatus: $connection,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function playbackChanged(PlaybackState $previous, array $data): ?PlaybackState
    {
        $isPlaying = $this->isPlaying($data['status'] ?? null);
        if ($isPlaying === null) {
            return null;
        }

        if ($data['status'] === 'idle') {
            return $this->with($previous, isPlaying: false, trackId: null, durationMs: null, progressMs: 0, sampledAt: CarbonImmutable::now());
        }

        return $this->with($previous, isPlaying: $isPlaying, progressMs: $previous->progressMs, sampledAt: CarbonImmutable::now());
    }

    /**
     * @param  callable(): MusicProvider  $provider
     * @param  array<string, mixed>  $data
     */
    private function trackChanged(callable $provider, PlaybackState $previous, array $data): ?PlaybackState
    {
        $item = $this->item($provider, $data['item'] ?? null);
        if ($item === null) {
            return null;
        }

        return $this->with($previous, isPlaying: true, trackId: $item['trackId'], durationMs: $item['durationMs'], progressMs: 0, sampledAt: CarbonImmutable::now());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function positionSync(PlaybackState $previous, array $data): ?PlaybackState
    {
        $position = $this->position($data);
        if ($position === false || $position === null) {
            return null;
        }

        return $this->with($previous, progressMs: $position['progressMs'], sampledAt: $position['sampledAt']);
    }

    /**
     * @param  callable(): MusicProvider  $provider
     * @param  array<string, mixed>  $data
     */
    private function queueChanged(callable $provider, PlaybackState $previous, array $data): ?PlaybackState
    {
        if (! is_array($data['upcoming'] ?? null)) {
            return null;
        }

        $upcoming = [];
        foreach ($data['upcoming'] as $entry) {
            $item = is_array($entry) ? $this->item($provider, $entry['item'] ?? null) : null;
            if ($item === null) {
                return null;
            }
            $upcoming[] = $item['trackId'];
        }

        return $this->with($previous, upcomingTrackIds: $upcoming);
    }

    private function isPlaying(mixed $status): ?bool
    {
        return match ($status) {
            'playing', 'buffering' => true,
            'paused', 'idle' => false,
            default => null,
        };
    }

    /**
     * Null when the frame has no position, false when it has a malformed one.
     *
     * @param  array<string, mixed>  $data
     * @return array{progressMs: int, sampledAt: CarbonImmutable}|false|null
     */
    private function position(array $data): array|false|null
    {
        $position = $data['position'] ?? null;
        if ($position === null) {
            return null;
        }
        if (! is_array($position) || ! is_int($position['position_ms'] ?? null) || $position['position_ms'] < 0) {
            return false;
        }

        $timestamp = $position['timestamp_ms'] ?? null;

        return [
            'progressMs' => $position['position_ms'],
            'sampledAt' => is_int($timestamp) ? CarbonImmutable::createFromTimestampMsUTC($timestamp) : CarbonImmutable::now(),
        ];
    }

    /**
     * @param  callable(): MusicProvider  $provider
     * @return array{trackId: string, durationMs: ?int}|null
     */
    private function item(callable $provider, mixed $item): ?array
    {
        if (! is_array($item) || ! is_string($item['uri'] ?? null)) {
            return null;
        }

        $trackId = $provider()->resolveTrackReference($item['uri']);
        if ($trackId === null) {
            return null;
        }

        $duration = $item['decorations']['playback']['duration_ms'] ?? null;

        return ['trackId' => $trackId, 'durationMs' => is_int($duration) ? $duration : null];
    }

    /**
     * @param  list<string>|null  $upcomingTrackIds
     */
    private function with(
        PlaybackState $previous,
        ?bool $isPlaying = null,
        string|false|null $trackId = false,
        int|false|null $durationMs = false,
        ?int $progressMs = null,
        ?CarbonImmutable $sampledAt = null,
        ?array $upcomingTrackIds = null,
        ?PlaybackConnectionStatus $connectionStatus = null,
    ): PlaybackState {
        return new PlaybackState(
            $isPlaying ?? $previous->isPlaying,
            $trackId === false ? $previous->trackId : $trackId,
            $trackId === false ? $previous->linkedFromId : null,
            $durationMs === false ? $previous->durationMs : $durationMs,
            $progressMs ?? $previous->progressMs,
            $sampledAt ?? $previous->sampledAt,
            $previous->shuffle,
            $previous->repeat,
            $previous->deviceId,
            $previous->deviceName,
            PlaybackSource::Encore,
            $upcomingTrackIds ?? $previous->upcomingTrackIds,
            $connectionStatus ?? $previous->connectionStatus,
        );
    }
}

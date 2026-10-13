<?php

namespace App\Services\Playback;

use App\Enums\Purpose;
use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\MusicProviderFactory;
use Carbon\CarbonImmutable;

class WebPlayerStateMapper implements PushedStateMapper
{
    public const string EVENT = 'client-state';

    public function __construct(protected MusicProviderFactory $providers) {}

    public function channelPrefix(): string
    {
        return 'private-webplayer.';
    }

    public function map(Party $party, string $event, array $data): ?PlaybackState
    {
        if ($event !== self::EVENT) {
            return null;
        }

        $uri = $data['uri'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
        if (! is_string($uri) || ! is_numeric($timestamp)) {
            return null;
        }

        $provider = $this->providers->forParty($party, Purpose::Control);
        $duration = $data['duration'] ?? null;
        $repeat = $data['repeat'] ?? 'off';

        return new PlaybackState(
            isPlaying: ! ($data['paused'] ?? true),
            trackId: $provider->resolveTrackReference($uri),
            linkedFromId: null,
            durationMs: is_numeric($duration) ? (int) $duration : null,
            progressMs: max(0, (int) ($data['position'] ?? 0)),
            sampledAt: CarbonImmutable::createFromTimestampMsUTC((int) $timestamp),
            shuffle: (bool) ($data['shuffle'] ?? false),
            repeat: in_array($repeat, ['off', 'context', 'track'], true) ? $repeat : 'off',
            deviceId: is_string($data['deviceId'] ?? null) ? $data['deviceId'] : null,
            deviceName: null,
            source: PlaybackSource::WebPlayer,
        );
    }
}

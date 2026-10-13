<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Models\Party;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Data\Device;
use App\Services\Music\MusicProviderFactory;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PlaybackControl
{
    public function __construct(
        protected MusicProviderFactory $providers,
        protected PlaybackSync $sync,
        protected QueueSelector $selector,
    ) {}

    public function perform(Party $party, string $action, ?string $deviceId = null): void
    {
        $provider = $this->providers->forParty($party, Purpose::Control);
        Log::debug("{$party}: Playback control -> {$action}()");

        match ($action) {
            'play' => $this->play($party, $provider, $deviceId),
            'pause' => $provider->pause(),
            'next' => $provider->next(),
            'previous' => $provider->seek(0),
            default => throw new InvalidArgumentException('Unsupported party control action.'),
        };
    }

    /**
     * @return list<Device>
     */
    public function devices(Party $party): array
    {
        return $this->providers->forParty($party, Purpose::Control)->devices();
    }

    public function deviceName(Party $party, string $deviceId): string
    {
        foreach ($this->devices($party) as $device) {
            if ($device->id === $deviceId) {
                return $device->name;
            }
        }

        return 'Unknown';
    }

    protected function play(Party $party, MusicProvider&ProviderPlayback $provider, ?string $deviceId): void
    {
        if ($deviceId !== null) {
            $provider->transferPlayback($deviceId);
        }
        $deviceId ??= $party->recent_device_id;
        $trackId = $provider->currentPlayback()->trackId
            ?? $party->song->provider_track_id
            ?? $party->history()->orderBy('played_at', 'desc')->first()?->song->provider_track_id;

        if ($trackId === null) {
            // We are stuck, let's add something to the queue and use it
            $this->sync->withLock($party, fn () => $this->selector->sendNext($party));
            $trackId = $party->upcoming()->orderBy('queued_at', 'desc')->first()?->song->provider_track_id;
        }

        $provider->play($deviceId, $trackId === null ? null : $this->sync->trackFor($party, $provider, $trackId));
    }
}

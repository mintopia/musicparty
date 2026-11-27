<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Events\Party\PlayerCommand;
use App\Exceptions\NoActiveDeviceException;
use App\Exceptions\PlaybackBusyException;
use App\Models\Party;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Data\Device;
use App\Services\Music\MusicProviderFactory;
use App\Services\Playback\ContextPlaylist;
use App\Services\Playback\PartyDashboardCache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PlaybackControl
{
    public function __construct(
        protected MusicProviderFactory $providers,
        protected PlaybackSync $sync,
        protected QueueSelector $selector,
        protected ContextPlaylist $context,
    ) {}

    public function perform(Party $party, string $action, ?string $deviceId = null, ?int $positionMs = null): void
    {
        try {
            $this->dispatchAction($party, $action, $deviceId, $positionMs);
        } finally {
            PartyDashboardCache::forget($party);
        }
    }

    protected function dispatchAction(Party $party, string $action, ?string $deviceId, ?int $positionMs): void
    {
        if ($action === 'play' && $party->hasFreshPushedPlayback()) {
            // Encore and the Web Player are Connect devices, so the Web API can start them; the pushed command is the fallback.
            try {
                $this->performViaProvider($party, $action, $deviceId, $positionMs);
            } catch (NoActiveDeviceException $exception) {
                if (! $this->sendToCurrentPlayer($party, $action, $deviceId, $positionMs)) {
                    throw $exception;
                }
            }

            return;
        }

        if ($this->sendToCurrentPlayer($party, $action, $deviceId, $positionMs)) {
            return;
        }

        $this->performViaProvider($party, $action, $deviceId, $positionMs);
    }

    protected function performViaProvider(Party $party, string $action, ?string $deviceId, ?int $positionMs): void
    {
        $provider = $this->providers->forParty($party, Purpose::Control);
        Log::debug("{$party}: Playback control -> {$action}()");

        match ($action) {
            'play' => $this->play($party, $provider, $deviceId),
            'pause' => $provider->pause(),
            'next' => $provider->next(),
            'previous' => $provider->seek(0),
            'seek' => $provider->seek($positionMs ?? throw new InvalidArgumentException('Seek requires a position.')),
            default => throw new InvalidArgumentException('Unsupported party control action.'),
        };
    }

    protected function sendToCurrentPlayer(Party $party, string $action, ?string $deviceId, ?int $positionMs): bool
    {
        $player = $party->playback_source;
        if ($player === null || ! $party->hasFreshPushedPlayback()) {
            return false;
        }

        // Device transfer is a Web API concept; pushed Players cannot honour it.
        if ($action === 'play' && $deviceId !== null) {
            return false;
        }

        $command = match ($action) {
            'play' => ['type' => 'play'],
            'pause' => ['type' => 'pause'],
            'next' => ['type' => 'next'],
            'previous' => ['type' => 'skip_prev'],
            'seek' => $positionMs === null ? null : ['type' => 'seek', 'position_ms' => $positionMs],
            default => null,
        };
        if ($command === null) {
            return false;
        }

        Log::debug("{$party}: Playback control -> {$action}() via {$player->value}");
        PlayerCommand::dispatch($party, $player, $command);

        return true;
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
        $state = $provider->currentPlayback();
        if ($state?->trackId !== null) {
            $provider->play($deviceId);

            return;
        }

        $trackId = $party->song->provider_track_id
            ?? $party->history()->orderBy('played_at', 'desc')->first()?->song->provider_track_id;

        if ($trackId === null) {
            // We are stuck, let's add something to the queue and use it
            $this->sync->withLock($party, fn () => $this->selector->sendNext($party));
            $trackId = $party->upcoming()->orderBy('queued_at', 'desc')->first()?->song->provider_track_id;
        }

        $track = $trackId === null ? null : $this->sync->trackFor($party, $provider, $trackId);
        if ($track === null) {
            $provider->play($deviceId);

            return;
        }

        $ran = $this->sync->withLock($party, fn () => $provider->playInContext($this->context->ensure($party, $provider), $track, $deviceId));
        if (! $ran) {
            throw new PlaybackBusyException;
        }
    }
}

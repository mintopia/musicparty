<?php

namespace App\Services\Dashboard;

use App\Enums\Purpose;
use App\Enums\StartTargetKind;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\MusicProviderFactory;
use App\Services\Playback\ContextPlaylist;
use App\Services\Playback\EncoreConnection;
use App\Services\Playback\PartyDashboardCache;
use App\Services\Playback\StartPlayback;
use App\Services\Playback\StartTarget;
use App\Services\PlaybackStatus;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PartyPlayerControl
{
    private const int POLLING_FRESH_SECONDS = 120;

    private const int IDLE_TRUST_SECONDS = 30;

    public function __construct(
        private readonly MusicProviderFactory $providers,
        private readonly PlaybackStatus $status,
        private readonly StartPlayback $startPlayback,
        private readonly ContextPlaylist $context,
        private readonly EncoreConnection $encore,
        private readonly PartyDevices $devices,
    ) {}

    /**
     * @param  UpcomingSong|false|null  $sent  The already-loaded Sent to Player Request (null for none); false to look it up.
     * @return array<string, mixed>
     */
    public function control(Party $party, UpcomingSong|false|null $sent = false): array
    {
        $needsRelink = $this->providers->partyNeedsRelink($party);
        $state = $needsRelink ? null : $this->playback($party);
        $recalled = $state ?? $this->status->recall($party);
        $playingFrom = $this->playingFrom($party, $recalled);
        $playing = $state?->isPlaying === true;

        return [
            'playingFrom' => $playingFrom,
            'nowPlaying' => $this->nowPlaying($party),
            'health' => $this->health($party, $recalled, $needsRelink),
            'start' => $this->start($party, $state, $playingFrom['kind'], $playing, $needsRelink, $sent),
        ];
    }

    public function playback(Party $party): ?PlaybackState
    {
        if ($this->providers->partyNeedsRelink($party)) {
            return null;
        }

        $remembered = $this->status->recall($party);
        if ($remembered !== null && $remembered->trackId === null && $remembered->sampledAt->greaterThan(now()->subSeconds(self::IDLE_TRUST_SECONDS))) {
            return $remembered;
        }

        try {
            return Cache::remember(PartyDashboardCache::playbackKey($party), PartyDashboardCache::READ_SECONDS, fn (): ?PlaybackState => $this->providers->forParty($party, Purpose::Control)->currentPlayback());
        } catch (ProviderBackoffException|ProviderRelinkRequiredException) {
            return null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array{kind: string, label: string|null}
     */
    private function playingFrom(Party $party, ?PlaybackState $state): array
    {
        if ($state === null || $state->trackId === null) {
            return ['kind' => 'nothing', 'label' => null];
        }

        $provider = $this->providers->forParty($party, Purpose::Control);
        if ($state->contextUri === null) {
            return ['kind' => $state->isPlaying ? 'unknown' : 'nothing', 'label' => null];
        }
        if ($this->context->isPartyContext($party, $provider, $state->contextUri)) {
            return ['kind' => 'party', 'label' => null];
        }

        try {
            $name = $provider->contextName($state->contextUri);
        } catch (Throwable $e) {
            report($e);
            $name = null;
        }
        $isCollection = $name !== null && preg_match('/^spotify:user:[^:]+:collection$/D', $state->contextUri) === 1;

        return ['kind' => 'outside', 'label' => $isCollection ? "{$party->user->nickname}'s {$name}" : $name];
    }

    /**
     * @return array{isRequest: bool, isFallback: bool, requestedBy: string|null}
     */
    private function nowPlaying(Party $party): array
    {
        $current = $party->song_id === null ? null : $party->current();
        $request = $current !== null && $current->song_id === $party->song_id ? $current->upcoming : null;

        $requestedBy = $request?->user?->nickname;

        return ['isRequest' => $requestedBy !== null, 'isFallback' => $request !== null && $request->user_id === null, 'requestedBy' => $requestedBy];
    }

    /**
     * @return array<string, mixed>
     */
    private function health(Party $party, ?PlaybackState $state, bool $needsRelink): array
    {
        $source = $party->playback_source;
        $connected = $source === PlaybackSource::Encore ? $this->encore->isConnected($party) : null;
        $fresh = $connected === true || ($source !== null && $party->playback_received_at !== null && $party->playback_received_at->greaterThan(
            now()->subSeconds($source->isPushed() ? Party::PUSHED_PLAYBACK_FRESH_SECONDS : self::POLLING_FRESH_SECONDS)
        ));

        return [
            'player' => [
                'source' => match ($source) {
                    PlaybackSource::Polling => 'polling',
                    PlaybackSource::Webhook => 'webhook',
                    PlaybackSource::WebPlayer => 'webplayer',
                    PlaybackSource::Encore => 'encore',
                    null => null,
                },
                'label' => $source?->label() ?? 'None',
                'fresh' => $fresh,
                'connected' => $connected,
            ],
            'device' => ['id' => $state->deviceId ?? $party->recent_device_id, 'name' => $state->deviceName ?? $party->device_name],
            'receivedAt' => $party->playback_received_at?->toIso8601String(),
            'link' => $needsRelink ? 'relink_required' : 'connected',
            'polling' => ! $party->poll ? 'off' : ($connected === true || $party->hasFreshPushedPlayback() ? 'backup' : 'on'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function start(Party $party, ?PlaybackState $state, string $playingFromKind, bool $playing, bool $needsRelink, UpcomingSong|false|null $sent): array
    {
        $empty = ['target' => null, 'devices' => [], 'selectedDeviceId' => null];

        if ($needsRelink) {
            return ['state' => 'relink_required'] + $empty;
        }
        if (Cache::get(StartPlayback::premiumFlagKey($party)) === true) {
            return ['state' => 'premium_required'] + $empty;
        }
        if ($playingFromKind === 'party' && $playing) {
            return ['state' => 'hidden'] + $empty;
        }

        $devices = $this->devices->devices($party);
        $usable = array_values(array_filter($devices, fn (Device $device): bool => ! $device->isRestricted && $device->id !== null));
        $target = $this->targetFor($party, $state, $sent);
        $kind = match (true) {
            $usable === [] => 'no_device',
            $playing => 'outside',
            $target?->kind === StartTargetKind::Resume => 'resume',
            default => 'idle',
        };

        return [
            'state' => $kind,
            'target' => $target === null ? null : $this->targetPresentation($party, $target),
            'devices' => array_map(fn (Device $device): array => $this->devices->devicePresentation($party, $device), $devices),
            'selectedDeviceId' => $this->devices->selectedDeviceId($party, $usable),
        ];
    }

    private function targetFor(Party $party, ?PlaybackState $state, UpcomingSong|false|null $sent): ?StartTarget
    {
        try {
            return $this->startPlayback->target($party, $state, $sent);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function targetPresentation(Party $party, StartTarget $target): array
    {
        $song = $target->request->song
            ?? ($party->song?->provider_track_id === $target->track->id ? $party->song : Song::fromTrack($target->track));

        return [
            'kind' => $target->kind->value,
            'track' => $song->toApi(),
            'positionMs' => $target->positionMs,
            'requestedBy' => $target->request?->user?->nickname,
        ];
    }
}

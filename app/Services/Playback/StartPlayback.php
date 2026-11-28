<?php

namespace App\Services\Playback;

use App\Enums\Purpose;
use App\Enums\StartMode;
use App\Enums\StartTargetKind;
use App\Exceptions\DeviceNotFoundException;
use App\Exceptions\DeviceRestrictedException;
use App\Exceptions\NoActiveDeviceException;
use App\Exceptions\NothingToPlayException;
use App\Exceptions\NotPlayableException;
use App\Exceptions\PartyRelinkRequiredException;
use App\Exceptions\PlaybackBusyException;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\FallbackPlaylist;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use App\Services\PlaybackControl;
use App\Services\PlaybackSync;
use App\Services\QueueSelector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StartPlayback
{
    public const int PREMIUM_FLAG_SECONDS = 3600;

    public function __construct(
        protected MusicProviderFactory $providers,
        protected PlaybackSync $sync,
        protected PlaybackControl $control,
        protected QueueSelector $selector,
        protected FallbackPlaylist $fallback,
        protected ContextPlaylist $context,
    ) {}

    public static function premiumFlagKey(Party $party): string
    {
        return "party.{$party->id}.premium-required";
    }

    /**
     * What Start Playback would play right now, without changing anything.
     */
    /**
     * @param  UpcomingSong|false|null  $sent  The already-loaded Sent to Player Request (null for none); false to look it up.
     */
    public function target(Party $party, ?PlaybackState $state, UpcomingSong|false|null $sent = false): ?StartTarget
    {
        $provider = $this->providers->forParty($party, Purpose::Control);

        if ($state !== null && $state->trackId !== null && ! $state->isPlaying && $state->progressMs > 0
            && $party->song !== null && $state->trackId === $party->song->provider_track_id) {
            $track = $this->sync->trackFor($party, $provider, $state->trackId);
            if ($track !== null) {
                return new StartTarget(StartTargetKind::Resume, $track, $state->progressMs, $party->current()?->upcoming, false);
            }
        }

        $sent = $sent === false ? $this->sentRequest($party) : $sent;
        if ($sent !== null) {
            return new StartTarget(StartTargetKind::Next, $sent->song->toTrack(), 0, $sent, true);
        }

        $request = $this->candidate($party);

        return $request === null ? null : new StartTarget(StartTargetKind::Select, $request->song->toTrack(), 0, $request, false);
    }

    public function start(Party $party, ?string $deviceId, StartMode $mode = StartMode::Start): StartTarget
    {
        if ($this->providers->partyNeedsRelink($party)) {
            throw new PartyRelinkRequiredException;
        }

        if ($party->backup_playlist_id !== null) {
            $this->fallback->tracks($party);
        }

        $result = null;
        $ran = $this->sync->withLock($party, function () use ($party, $deviceId, $mode, &$result): void {
            $result = $this->startLocked($party, $deviceId, $mode);
        });
        if (! $ran || $result === null) {
            throw new PlaybackBusyException;
        }

        Cache::forget(self::premiumFlagKey($party));
        $this->afterChange($party);

        return $result;
    }

    public function playNow(Party $party, UpcomingSong $request): void
    {
        if ($request->flagged_at !== null || $request->played()->exists()) {
            throw new NotPlayableException;
        }

        $sent = $this->sentRequest($party);
        if ($request->queued_at !== null && ! $sent?->is($request)) {
            throw new NotPlayableException;
        }
        if ($sent?->is($request)) {
            $this->control->perform($party, 'next');
            $this->afterChange($party);

            return;
        }

        $ran = $this->sync->withLock($party, function () use ($party, $request): void {
            $provider = $this->providers->forParty($party, Purpose::Control);
            $playlistId = $this->context->ensure($party, $provider);
            $track = $request->song->toTrack();
            $provider->playInContext($playlistId, $track);
            $this->stamp($party, $provider, $request, $track, $this->stampBeforeSent($party));
        });
        if (! $ran) {
            throw new PlaybackBusyException;
        }

        $this->afterChange($party);
    }

    protected function startLocked(Party $party, ?string $deviceId, StartMode $mode): StartTarget
    {
        $provider = $this->providers->forParty($party, Purpose::Control);
        $playlistId = $this->context->ensure($party, $provider);
        $device = $this->resolveDevice($party, $provider, $deviceId);
        $target = $this->resolveTarget($party, $provider, $mode);
        $target = new StartTarget($target->kind, $target->track, $target->positionMs, $target->request, $target->alreadySent, $device);

        if ($target->alreadySent) {
            $provider->playInContext($playlistId, $target->track, $device);
            $provider->next();
        } else {
            $provider->playInContext($playlistId, $target->track, $device, $target->positionMs);
            if ($target->kind === StartTargetKind::Select && $target->request !== null) {
                $this->stamp($party, $provider, $target->request, $target->track, now());
            }
        }

        Log::info("{$party}: Started playback of {$target->track->name} on {$device}");
        $party->forceFill(['recent_device_id' => $device])->saveQuietly();

        return $target;
    }

    protected function resolveTarget(Party $party, MusicProvider&ProviderPlayback $provider, StartMode $mode): StartTarget
    {
        if ($mode === StartMode::Restart) {
            $track = $party->song === null ? null : $this->sync->trackFor($party, $provider, $party->song->provider_track_id);

            return $track === null
                ? throw new NothingToPlayException
                : new StartTarget(StartTargetKind::Resume, $track, 0, $party->current()?->upcoming, false);
        }

        $state = $provider->currentPlayback();
        $target = $this->target($party, $state);
        if ($target === null && $party->backup_playlist_id !== null) {
            $this->fallback->backfill($party);
            $target = $this->target($party, $state);
        }

        return $target ?? throw new NothingToPlayException;
    }

    protected function resolveDevice(Party $party, MusicProvider&ProviderPlayback $provider, ?string $deviceId): string
    {
        $devices = array_values(array_filter($provider->devices(), fn (Device $device): bool => $device->id !== null));

        if ($deviceId !== null) {
            foreach ($devices as $device) {
                if ($device->id === $deviceId) {
                    return $device->isRestricted ? throw new DeviceRestrictedException : (string) $device->id;
                }
            }

            throw new DeviceNotFoundException;
        }

        $usable = array_values(array_filter($devices, fn (Device $device): bool => ! $device->isRestricted));
        foreach ($usable as $device) {
            if ($device->isActive) {
                return (string) $device->id;
            }
        }
        foreach ([$party->device_id, $party->recent_device_id] as $preferred) {
            foreach ($usable as $device) {
                if ($preferred !== null && $device->id === $preferred) {
                    return (string) $device->id;
                }
            }
        }

        return isset($usable[0]) ? (string) $usable[0]->id : throw new NoActiveDeviceException('No device available to start playback on');
    }

    /**
     * A queued_at that sorts just before the Sent Request but after the last played one, so history linking keeps working.
     */
    protected function stampBeforeSent(Party $party): mixed
    {
        $sent = $this->sentRequest($party);
        if ($sent === null || $sent->queued_at === null) {
            return now();
        }

        $base = $sent->queued_at->copy();
        $stamp = $base->copy()->subSecond();
        $lastPlayed = $party->upcoming()->whereNotNull('queued_at')->has('played')->orderByDesc('queued_at')->first();
        if ($lastPlayed?->queued_at !== null && $stamp->lessThanOrEqualTo($lastPlayed->queued_at)) {
            $sent->queued_at = $base->copy()->addSecond();
            $sent->save();

            return $base;
        }

        return $stamp;
    }

    protected function candidate(Party $party): ?UpcomingSong
    {
        if (! $party->weighted) {
            return $this->selector->pick($party, 1)->first();
        }

        $key = "party.{$party->id}.start-pick";
        $id = Cache::get($key);
        $cached = $id === null ? null : $party->upcoming()->whereNull('queued_at')->whereNull('flagged_at')->with('song')->whereKey($id)->first();
        if ($cached !== null) {
            return $cached;
        }

        $request = $this->selector->pick($party, 1)->first();
        if ($request !== null) {
            Cache::put($key, $request->id, 30);
        }

        return $request;
    }

    protected function stamp(Party $party, MusicProvider&ProviderPlayback $provider, UpcomingSong $request, Track $track, mixed $queuedAt): void
    {
        $request->queued_at = $queuedAt ?? now();
        $request->save();
        Cache::forget("party.{$party->id}.start-pick");
        if ($party->history_playlist_id !== null) {
            $provider->appendToPlaylist($party->history_playlist_id, [$track]);
        }
    }

    protected function sentRequest(Party $party): ?UpcomingSong
    {
        return $party->upcoming()
            ->whereNotNull('queued_at')
            ->doesntHave('played')
            ->with('song')
            ->orderByDesc('queued_at')
            ->first();
    }

    protected function afterChange(Party $party): void
    {
        $this->sync->sync($party);
        PartyDashboardCache::forget($party);
    }
}

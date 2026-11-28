<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Events\Party\UpdatedEvent;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Data\PlaybackConnectionStatus;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use App\Services\Playback\ContextPlaylist;
use App\Services\Playback\PartyDashboardCache;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlaybackSync
{
    public const LOCK_SECONDS = 120;

    public const int PUSHED_WAIT_SECONDS = 15;

    private const int RECEIVED_ORDER_SECONDS = 300;

    private const int POLLING_FRESH_SECONDS = 120;

    private const int SEEK_THRESHOLD_MS = 3000;

    private bool $seekDetected = false;

    public function __construct(
        protected MusicProviderFactory $providers,
        protected QueueSelector $selector,
        protected PlaybackStatus $status,
    ) {}

    /**
     * Polls the Music Provider for the Party's playback and applies it. Returns false when another sync holds the lock.
     */
    public function sync(Party $party, PlaybackSource $source = PlaybackSource::Polling): bool
    {
        return $this->withLock($party, function () use ($party, $source): void {
            Log::info("{$party}: Updating state");
            if ($party->hasFreshPushedPlayback()) {
                Log::debug("{$party}: Pushed playback state is fresh, not polling");

                return;
            }
            if ($party->poll && $party->updatedWithinDebounce()) {
                Log::debug("{$party}: Already updated state recently");

                return;
            }

            $progressOnly = false;
            try {
                if ($this->providers->partyNeedsRelink($party)) {
                    Log::debug("{$party}: Provider needs re-linking, skipping update");

                    return;
                }

                $provider = $this->providers->forParty($party, Purpose::Control);
                $playback = $this->forcePlayback($party, $provider, $provider->currentPlayback());
                $progressOnly = $this->applyState($party, $provider, $playback?->withSource($source) ?? PlaybackState::idle($source));
            } finally {
                Cache::put($party->debounceKey(), true, Party::UPDATE_DEBOUNCE_SECONDS);
                if (! $progressOnly) {
                    $this->touchLastUpdated($party);
                }
            }
        });
    }

    /**
     * Single entry point for a known playback state: updates the current track, records the Played Track, tops up the Queue and broadcasts.
     * Waits for the per-Party lock and resolves the state inside it, so a pushed frame merges onto the state applied before it.
     * A frame received before the last applied one is skipped. Encore is authoritative, so its frames are not ordered by sampledAt; other sources drop states sampled before the last applied one.
     * Returns false when the lock could not be acquired in time.
     *
     * @param  PlaybackState|callable(): ?PlaybackState  $state
     */
    public function apply(Party $party, PlaybackState|callable $state, ?int $receivedAtMs = null): bool
    {
        return $this->withLock($party, function () use ($party, $state, $receivedAtMs): void {
            $party->refresh();
            $receivedKey = "party-pushed-received:{$party->id}";
            $lastReceivedMs = Cache::get($receivedKey);
            if ($receivedAtMs !== null && is_int($lastReceivedMs) && $receivedAtMs < $lastReceivedMs) {
                Log::debug("{$party}: Dropping pushed frame received before the last applied one");

                return;
            }

            $state = is_callable($state) ? $state() : $state;
            if ($state === null) {
                return;
            }
            if ($state->source !== PlaybackSource::Encore && $this->orderingStamp($party)?->greaterThan($state->sampledAt)) {
                Log::debug("{$party}: Dropping stale-ordered playback state from {$state->source->value}");

                return;
            }

            $this->applyState($party, $this->providers->forParty($party, Purpose::Control), $state);
            if ($receivedAtMs !== null) {
                Cache::put($receivedKey, $receivedAtMs, self::RECEIVED_ORDER_SECONDS);
            }
        }, self::PUSHED_WAIT_SECONDS);
    }

    /**
     * Runs the callback under the per-Party sync lock, broadcasting once if the rendered state changed.
     * Returns false when another caller holds the lock, after waiting up to $waitSeconds for it.
     *
     * @param  callable(): mixed  $callback
     */
    public function withLock(Party $party, callable $callback, int $waitSeconds = 0): bool
    {
        if (! config('musicparty.prevent_overlapping_updates')) {
            $this->coalescingBroadcasts($party, $callback);

            return true;
        }

        $lock = Cache::lock("party-sync:{$party->id}", self::LOCK_SECONDS);
        try {
            $acquired = $waitSeconds > 0 ? $lock->block($waitSeconds) : $lock->get();
        } catch (LockTimeoutException) {
            $acquired = false;
        }
        if (! $acquired) {
            Log::debug("{$party}: Sync already running, skipping");

            return false;
        }

        try {
            $this->coalescingBroadcasts($party, $callback);
        } finally {
            $lock->release();
        }

        return true;
    }

    protected function orderingStamp(Party $party): ?CarbonInterface
    {
        $persisted = $party->playback_sampled_at;
        $recalled = $this->status->recall($party)?->sampledAt;

        return match (true) {
            $persisted === null => $recalled,
            $recalled === null => $persisted,
            default => $recalled->greaterThan($persisted) ? $recalled : $persisted,
        };
    }

    protected function deviatesFromExtrapolation(?PlaybackState $previous, PlaybackState $state): bool
    {
        if ($previous === null) {
            return false;
        }
        $elapsedMs = $previous->isPlaying ? max(0, $previous->sampledAt->diffInMilliseconds($state->sampledAt, false)) : 0;

        return abs($state->progressMs - ($previous->progressMs + $elapsedMs)) > self::SEEK_THRESHOLD_MS;
    }

    public function nextUpdateDelay(Party $party): ?int
    {
        if (! $party->poll) {
            return null;
        }

        $state = $this->status->recall($party);
        if (! $party->song || $state === null || ! $state->isPlaying) {
            return 60;
        }

        $remaining = ($party->song->length - $state->progressMs) / 1000;
        if ($remaining > 60) {
            return 60;
        }

        return max(5, (int) floor($remaining / 2));
    }

    public function trackFor(Party $party, MusicProvider $provider, string $trackId): ?Track
    {
        $song = Song::where(['provider_id' => $party->provider_id, 'provider_track_id' => $trackId])->first();

        return $song?->toTrack() ?? $provider->getTrack($trackId);
    }

    /**
     * @param  callable(): mixed  $sync
     */
    protected function coalescingBroadcasts(Party $party, callable $sync): void
    {
        $party->suppressRenderedBroadcasts(true);
        $this->seekDetected = false;
        $stateBefore = $this->renderedStateFingerprint($party);

        try {
            $sync();
        } finally {
            $party->suppressRenderedBroadcasts(false);
            if ($this->consumeSeek() || $this->renderedStateFingerprint($party) !== $stateBefore) {
                UpdatedEvent::dispatch($party);
            }
        }
    }

    protected function consumeSeek(): bool
    {
        $seeked = $this->seekDetected;
        $this->seekDetected = false;

        return $seeked;
    }

    protected function renderedStateFingerprint(Party $party): string
    {
        $state = (array) $party->getState();
        unset($state['updated_at']);
        $status = (array) $state['status'];
        unset($status['updatedAt'], $status['sampledAt'], $status['progress'], $status['progressMs']);
        $state['status'] = $status;

        return json_encode($state, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns true when only progress changed, in which case nothing is persisted or broadcast.
     */
    protected function applyState(Party $party, MusicProvider&ProviderPlayback $provider, PlaybackState $state): bool
    {
        $previous = $this->status->recall($party);
        $seeked = $previous !== null && $previous->differsOnlyInProgress($state) && $this->deviatesFromExtrapolation($previous, $state);
        $this->seekDetected = $seeked;
        $progressOnly = $previous !== null
            && ! $seeked
            && $previous->differsOnlyInProgress($state)
            && $party->playback_received_at !== null
            && $party->playback_received_at->greaterThan(now()->subSeconds($this->receivedHeartbeatSeconds($state->source)));
        $this->status->remember($party, $state);
        $this->status->markSynced($party);
        if ($progressOnly) {
            if ($party->active) {
                $this->selector->run($party);
            }

            return true;
        }

        PartyDashboardCache::forget($party);
        $party->playback_source = $state->source;
        $party->playback_received_at = $state->connectionStatus === null || $state->connectionStatus === PlaybackConnectionStatus::Connected ? now() : null;
        $party->playback_sampled_at = Carbon::instance($state->sampledAt);

        if (! $party->active) {
            Log::debug("{$party}: Party is not active");
            // Still update current song, in case they're using it manually and still want it reflected on screen
            $this->updateCurrentTrack($party, $provider, $state);
            $party->save();

            return false;
        }

        try {
            $this->updateCurrentTrack($party, $provider, $state);
            $this->selector->run($party);
        } finally {
            $this->touchLastUpdated($party);
        }
        Log::info("{$party}: Finished updating state");

        return false;
    }

    /**
     * How stale the persisted received-at may get before a progress-only state refreshes it, a third of the freshness window health checks use.
     */
    protected function receivedHeartbeatSeconds(PlaybackSource $source): int
    {
        return intdiv($source->isPushed() ? Party::PUSHED_PLAYBACK_FRESH_SECONDS : self::POLLING_FRESH_SECONDS, 3);
    }

    protected function touchLastUpdated(Party $party): void
    {
        $party->last_updated_at = now();
        $party->save();
    }

    protected function updateCurrentTrack(Party $party, MusicProvider $provider, PlaybackState $state): void
    {
        Log::debug("{$party}: Updating current song");

        if ($state->trackId === null) {
            if ($party->song_id) {
                Log::info("{$party}: Not playing anything");
                $party->song_id = null;
                $party->song_started_at = null;
                $party->save();
            }

            return;
        }

        if ($state->deviceId !== null && $state->deviceId !== $party->recent_device_id) {
            $party->recent_device_id = $state->deviceId;
        }

        $song = Song::where(['provider_id' => $party->provider_id, 'provider_track_id' => $state->trackId])->first();
        if ($song === null) {
            $track = $provider->getTrack($state->trackId);
            if ($track === null) {
                Log::warning("{$party}: Unable to resolve playing track {$state->trackId}");

                return;
            }
            $song = Song::fromTrack($track);
        }

        if ($song->id != $party->song_id) {
            Log::info("{$party}: Updating current song to {$song}");
            $party->song()->associate($song);
            $party->song_started_at = Carbon::instance($state->sampledAt->subMillis($state->progressMs));

            $playedSong = new PlayedSong;
            $playedSong->song()->associate($song);
            $playedSong->party()->associate($party);
            $playedSong->played_at = $party->song_started_at;
            $playedSong->relinked_from = $state->linkedFromId;
            $playedSong->findRequestedSong();
            $playedSong->save();
        }

        if ($party->isDirty()) {
            $party->save();
        }
    }

    protected function forcePlayback(Party $party, MusicProvider&ProviderPlayback $provider, ?PlaybackState $state): ?PlaybackState
    {
        if (! $party->force || ! $party->device_id) {
            return $state;
        }

        if ($state?->trackId !== null) {
            if ($state->isPlaying) {
                return $state;
            }
            if ($party->song && $state->trackId === $party->song->provider_track_id && $state->deviceId === $party->device_id) {
                // Our current song, on our device, and it's paused. Let's not play anything - they can control it using the webpage
                return $state;
            }
        }

        $trackId = $state?->trackId;
        if ($trackId === null && $party->song !== null) {
            $trackId = $party->song->provider_track_id;
        }
        foreach ($provider->devices() as $device) {
            if ($device->id === $party->device_id) {
                Log::info("{$party}: Forcing playback on {$device->name}");
                $track = $trackId === null ? null : $this->trackFor($party, $provider, $trackId);
                if ($track === null) {
                    $provider->play($party->device_id);
                } else {
                    $provider->playInContext(app(ContextPlaylist::class)->ensure($party, $provider), $track, $party->device_id);
                }

                return $provider->currentPlayback();
            }
        }

        return $state;
    }
}

<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Events\Party\UpdatedEvent;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Services\Contracts\MusicProvider;
use App\Services\Contracts\ProviderPlayback;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackConnectionStatus;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\MusicProviderFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlaybackSync
{
    public const LOCK_SECONDS = 30;

    private const int POLL_DEBOUNCE_SECONDS = 5;

    public function __construct(
        protected MusicProviderFactory $providers,
        protected QueueSelector $selector,
        protected PlaybackStatus $status,
    ) {}

    /**
     * Polls the Music Provider for the Party's playback and applies it. Returns false when another sync holds the lock.
     */
    public function sync(Party $party): bool
    {
        return $this->withLock($party, function () use ($party): void {
            Log::info("{$party}: Updating state");
            if ($party->hasFreshPushedPlayback()) {
                Log::debug("{$party}: Pushed playback state is fresh, not polling");

                return;
            }
            if ($party->poll && $party->last_updated_at > now()->subSeconds(self::POLL_DEBOUNCE_SECONDS)) {
                Log::debug("{$party}: Already updated state recently");

                return;
            }

            try {
                if ($this->providers->partyNeedsRelink($party)) {
                    Log::debug("{$party}: Provider needs re-linking, skipping update");

                    return;
                }

                $provider = $this->providers->forParty($party, Purpose::Control);
                $playback = $this->forcePlayback($party, $provider, $provider->currentPlayback());
                $this->applyState($party, $provider, $playback ?? PlaybackState::idle(PlaybackSource::Polling));
            } finally {
                $party->last_updated_at = now();
                $party->save();
            }
        });
    }

    /**
     * Single entry point for a known playback state: updates the current track, records the Played Track, tops up the Queue and broadcasts.
     * Returns false when another sync holds the lock.
     */
    public function apply(Party $party, PlaybackState $state): bool
    {
        return $this->withLock($party, function () use ($party, $state): void {
            $party->refresh();
            if ($party->playback_sampled_at !== null && $state->sampledAt->lessThan($party->playback_sampled_at)) {
                Log::debug("{$party}: Dropping stale-ordered playback state from {$state->source->value}");

                return;
            }

            $this->applyState($party, $this->providers->forParty($party, Purpose::Control), $state);
        });
    }

    /**
     * Runs the callback under the per-Party sync lock, broadcasting once if the rendered state changed. Returns false when another caller holds the lock.
     *
     * @param  callable(): mixed  $callback
     */
    public function withLock(Party $party, callable $callback): bool
    {
        if (! config('musicparty.prevent_overlapping_updates')) {
            $this->coalescingBroadcasts($party, $callback);

            return true;
        }

        $lock = Cache::lock("party-sync:{$party->id}", self::LOCK_SECONDS);
        if (! $lock->get()) {
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
        $stateBefore = $this->renderedStateFingerprint($party);

        try {
            $sync();
        } finally {
            $party->suppressRenderedBroadcasts(false);
            if ($this->renderedStateFingerprint($party) !== $stateBefore) {
                UpdatedEvent::dispatch($party);
            }
        }
    }

    protected function renderedStateFingerprint(Party $party): string
    {
        $state = (array) $party->getState();
        unset($state['updated_at']);
        $status = (array) $state['status'];
        unset($status['updatedAt']);
        $state['status'] = $status;

        return json_encode($state, JSON_THROW_ON_ERROR);
    }

    protected function applyState(Party $party, MusicProvider&ProviderPlayback $provider, PlaybackState $state): void
    {
        $this->status->remember($party, $state);
        $party->playback_source = $state->source;
        $party->playback_received_at = $state->connectionStatus === null || $state->connectionStatus === PlaybackConnectionStatus::Connected ? now() : null;
        $party->playback_sampled_at = Carbon::instance($state->sampledAt);

        if (! $party->active) {
            Log::debug("{$party}: Party is not active");
            // Still update current song, in case they're using it manually and still want it reflected on screen
            $this->updateCurrentTrack($party, $provider, $state);
            $party->save();

            return;
        }

        try {
            $this->updateCurrentTrack($party, $provider, $state);
            $this->selector->run($party);
        } finally {
            $party->last_updated_at = now();
            $party->save();
        }
        Log::info("{$party}: Finished updating state");
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
                $provider->play($party->device_id, $track);

                return $provider->currentPlayback();
            }
        }

        return $state;
    }
}

<?php

namespace App\Domain\Playback\Players;

use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\Control;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\Exceptions\UnsupportedControl;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PlaybackStatus;
use App\Events\Player\BrowserPlayerCommandEvent;
use App\Models\Party;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class BrowserPlayer implements Player
{
    public const string KIND = 'browser';

    private const int CLAIM_TTL_SECONDS = 180;

    private const int STATE_TTL_SECONDS = 86400;

    private ?string $partyCode = null;

    public function forParty(Party $party): self
    {
        $this->partyCode = $party->code;

        return $this;
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function compatibleProviders(): array
    {
        return [SpotifyMusicProvider::ID];
    }

    public function feedMode(): FeedMode
    {
        return FeedMode::JustInTime;
    }

    public function requiresHostAccount(): bool
    {
        return true;
    }

    public function supports(Control $control): bool
    {
        return false;
    }

    public function state(): PlaybackState
    {
        $stored = $this->partyCode === null ? null : Cache::get($this->stateKey());

        if (! is_array($stored) || ! isset($stored['status'])) {
            return PlaybackState::stopped();
        }

        $track = isset($stored['provider'], $stored['track']) ? new TrackReference((string) $stored['provider'], (string) $stored['track']) : null;

        return new PlaybackState(
            PlaybackStatus::from((string) $stored['status']),
            $track,
            (int) ($stored['position_ms'] ?? 0),
            isset($stored['updated_at']) ? CarbonImmutable::parse((string) $stored['updated_at']) : null,
            isset($stored['duration_ms']) ? (int) $stored['duration_ms'] : null,
        );
    }

    public function claim(string $tabId): bool
    {
        $holder = $this->holder();

        if ($holder !== null && $holder !== $tabId) {
            return false;
        }

        Cache::put($this->claimKey(), $tabId, self::CLAIM_TTL_SECONDS);

        return true;
    }

    public function holds(string $tabId): bool
    {
        return $this->holder() === $tabId;
    }

    public function isConnected(): bool
    {
        return $this->holder() !== null;
    }

    public function release(string $tabId): bool
    {
        if (! $this->holds($tabId)) {
            return false;
        }

        Cache::forget($this->claimKey());
        Cache::forget($this->stateKey());

        return true;
    }

    public function remember(string $tabId, PlaybackState $state): bool
    {
        if (! $this->claim($tabId)) {
            return false;
        }

        Cache::put($this->stateKey(), [
            'status' => $state->status->value,
            'provider' => $state->currentTrack?->providerId,
            'track' => $state->currentTrack?->providerTrackId,
            'position_ms' => $state->positionMs,
            'duration_ms' => $state->durationMs,
            'updated_at' => ($state->updatedAt ?? CarbonImmutable::now())->toIso8601String(),
        ], self::STATE_TTL_SECONDS);

        return true;
    }

    public function enqueue(string $providerId, string $providerTrackId): void
    {
        if ($this->partyCode === null || ! $this->isConnected()) {
            throw new PlayerDisconnectedException('No browser tab holds the Player role.');
        }

        BrowserPlayerCommandEvent::dispatch($this->partyCode, $providerId, $providerTrackId);
    }

    public function play(): void
    {
        throw UnsupportedControl::for(Control::Play);
    }

    public function pause(): void
    {
        throw UnsupportedControl::for(Control::Pause);
    }

    public function skip(): void
    {
        throw UnsupportedControl::for(Control::Skip);
    }

    public function seek(int $positionMs): void
    {
        throw UnsupportedControl::for(Control::Seek);
    }

    public function volume(int $level): void
    {
        throw UnsupportedControl::for(Control::Volume);
    }

    private function holder(): ?string
    {
        if ($this->partyCode === null) {
            return null;
        }

        $holder = Cache::get($this->claimKey());

        return is_string($holder) ? $holder : null;
    }

    private function claimKey(): string
    {
        return "playback.browser.{$this->partyCode}.claim";
    }

    private function stateKey(): string
    {
        return "playback.browser.{$this->partyCode}.state";
    }
}

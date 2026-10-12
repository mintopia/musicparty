<?php

namespace App\Domain\Playback\Players;

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Contracts\BindsToParty;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\Control;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\Exceptions\PlaybackDeviceNotFoundException;
use App\Domain\Playback\Exceptions\PlayerCommandRejectedException;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\Exceptions\PlayerEnqueueUnconfirmedException;
use App\Domain\Playback\Exceptions\PlayerRateLimitedException;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PlaybackStatus;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;

class PollingPlayer implements BindsToParty, Player
{
    public const string KIND = 'polling';

    private const int STATE_TTL_SECONDS = 86400;

    private const int DEVICE_MISSING_LOG_SECONDS = 60;

    private ?string $partyCode = null;

    public function __construct(
        private readonly PlaybackClient $client,
        private readonly AuthorisesHost $hosts,
        private readonly RecordPartyLogEntry $record,
    ) {}

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
        return FeedMode::Ahead;
    }

    public function requiresHostAccount(): bool
    {
        return true;
    }

    public function supports(Control $control): bool
    {
        return true;
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

    public function hostAccount(Party $party): ?LinkedAccount
    {
        $host = $party->user;

        return ! $host instanceof User ? null : $this->hosts->linkedAccountFor($host, $party->music_provider);
    }

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function fetchState(Party $party, LinkedAccount $account): PlaybackState
    {
        return $this->client->currentPlayback((string) $account->getKey());
    }

    public function remember(PlaybackState $state): void
    {
        Cache::put($this->stateKey(), [
            'status' => $state->status->value,
            'provider' => $state->currentTrack?->providerId,
            'track' => $state->currentTrack?->providerTrackId,
            'position_ms' => $state->positionMs,
            'duration_ms' => $state->durationMs,
            'updated_at' => ($state->updatedAt ?? CarbonImmutable::now())->toIso8601String(),
        ], self::STATE_TTL_SECONDS);
    }

    public function lastSeenTrackId(): ?string
    {
        $seen = Cache::get($this->seenKey());

        return is_string($seen) ? $seen : null;
    }

    public function markSeen(?string $providerTrackId): void
    {
        if ($providerTrackId === null) {
            Cache::forget($this->seenKey());

            return;
        }

        Cache::put($this->seenKey(), $providerTrackId, self::STATE_TTL_SECONDS);
    }

    public function enqueue(string $providerId, string $providerTrackId): void
    {
        $party = $this->partyCode === null ? null : Party::findByCode($this->partyCode);
        $account = $party === null ? null : $this->hostAccount($party);

        if ($party === null || $account === null) {
            throw new PlayerDisconnectedException('The Host account is not linked.');
        }

        try {
            $this->client->queueTrack($providerTrackId, (string) $account->getKey(), $party->playback_device_id);
        } catch (ProviderTemporaryFailure $failure) {
            throw match (true) {
                $failure->retryAfterSeconds !== null => new PlayerRateLimitedException($failure->retryAfterSeconds),
                $failure->outcomeUnknown => new PlayerEnqueueUnconfirmedException($failure->getMessage()),
                default => new PlayerDisconnectedException($failure->getMessage()),
            };
        } catch (ProviderUnavailableException $failure) {
            throw new PlayerDisconnectedException($failure->getMessage());
        }
    }

    public function play(): void
    {
        $this->command(fn (PlaybackClient $client, string $account, ?string $device) => $client->play($account, $device));
    }

    public function pause(): void
    {
        $this->command(fn (PlaybackClient $client, string $account, ?string $device) => $client->pause($account, $device));
    }

    public function skip(): void
    {
        $this->command(fn (PlaybackClient $client, string $account, ?string $device) => $client->next($account, $device));
    }

    public function seek(int $positionMs): void
    {
        $this->command(fn (PlaybackClient $client, string $account, ?string $device) => $client->seek($positionMs, $account, $device));
    }

    public function volume(int $level): void
    {
        $this->command(fn (PlaybackClient $client, string $account, ?string $device) => $client->volume($level, $account, $device));
    }

    /**
     * @param  Closure(PlaybackClient, string, ?string): void  $send
     */
    private function command(Closure $send): void
    {
        $party = $this->boundParty() ?? throw new PlayerDisconnectedException('The Host account is not linked.');
        $account = $this->hostAccount($party) ?? throw new PlayerDisconnectedException('The Host account is not linked.');

        try {
            $send($this->client, (string) $account->getKey(), $party->playback_device_id);
        } catch (PlaybackDeviceNotFoundException $failure) {
            $this->reportMissingDevice($party, $failure->deviceId);

            $name = $party->playback_device_name ?? $failure->deviceId;

            throw new PlayerCommandRejectedException("Spotify cannot find the playback device \"{$name}\". Open Spotify on it, or choose another device in the Party settings.");
        } catch (ProviderTemporaryFailure $failure) {
            throw $failure->retryAfterSeconds !== null
                ? new PlayerRateLimitedException($failure->retryAfterSeconds)
                : new PlayerDisconnectedException($failure->getMessage());
        } catch (ProviderUnavailableException $failure) {
            throw new PlayerDisconnectedException($failure->getMessage());
        }
    }

    private function reportMissingDevice(Party $party, string $deviceId): void
    {
        if (! Cache::add("playback.polling.{$party->code}.device_missing", true, self::DEVICE_MISSING_LOG_SECONDS)) {
            return;
        }

        ($this->record)($party, 'player.device_missing', subject: $party->playback_device_name ?? $deviceId, details: ['device_id' => $deviceId, 'device_name' => $party->playback_device_name], systemActor: 'player');
    }

    private function boundParty(): ?Party
    {
        return $this->partyCode === null ? null : Party::findByCode($this->partyCode);
    }

    private function stateKey(): string
    {
        return "playback.polling.{$this->partyCode}.state";
    }

    private function seenKey(): string
    {
        return "playback.polling.{$this->partyCode}.seen";
    }
}

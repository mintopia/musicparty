<?php

namespace App\Domain\Playback\Jobs;

use App\Domain\Music\Exceptions\HostAccountNeedsRelink;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Party\Actions\PauseParty;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Exceptions\InvalidPartyTransition;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Playback\Players\PollingPlayer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PollPlayback implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const int CHAIN_GRACE_SECONDS = 60;

    public int $tries = 10;

    /**
     * @param  bool  $reschedule  false for a one-off "check now" poll that leaves the running chain alone
     */
    public function __construct(public readonly string $partyCode, public readonly bool $reschedule = true, public readonly ?string $chainToken = null)
    {
        $this->onQueue('polling');
    }

    public static function start(Party $party): bool
    {
        if ($party->state !== PartyState::Live || ! app(PartyPlayers::class)->for($party) instanceof PollingPlayer) {
            return false;
        }

        $token = Str::random(16);

        if (! Cache::add(self::chainKey($party->code), $token, self::CHAIN_GRACE_SECONDS)) {
            return false;
        }

        self::dispatch($party->code, true, $token);

        return true;
    }

    public static function checkNow(string $partyCode): void
    {
        self::dispatch($partyCode, false);
    }

    public static function delayFor(PlaybackState $state): int
    {
        $minimum = self::setting('min_delay_seconds');
        $normal = self::setting('normal_delay_seconds');

        if ($state->status !== PlaybackStatus::Playing || $state->currentTrack === null) {
            return self::setting('idle_delay_seconds');
        }

        if ($state->durationMs === null) {
            return $normal;
        }

        $untilEnd = (int) ceil(max(0, $state->durationMs - $state->positionMs) / 1000) + 1;

        return max($minimum, min($normal, $untilEnd));
    }

    public static function backoffDelay(int $failures, ?int $retryAfterSeconds): int
    {
        $exponential = min(self::setting('backoff_cap_seconds'), self::setting('normal_delay_seconds') * 2 ** (min(max($failures, 1), 16) - 1));

        return max($exponential, $retryAfterSeconds ?? 0);
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        $overlap = new WithoutOverlapping($this->partyCode)->expireAfter(30);

        return [$this->reschedule ? $overlap->releaseAfter(2) : $overlap->dontRelease()];
    }

    public function handle(PartyPlayers $players, PlaybackCoordinator $coordinator): void
    {
        if ($this->isStale()) {
            return;
        }

        $party = Party::findByCode($this->partyCode);
        $player = $party === null ? null : $players->for($party);

        if ($party === null || $party->state !== PartyState::Live || ! $player instanceof PollingPlayer) {
            $this->stop();

            return;
        }

        $account = $player->hostAccount($party);

        if ($account === null) {
            $this->pauseForHost($party, 'player.host_account_unlinked');

            return;
        }

        if ($account->needs_relink) {
            $this->pauseForHost($party, 'player.host_account_needs_relink');

            return;
        }

        try {
            $state = $player->fetchState($party, $account);
        } catch (HostAccountNeedsRelink) {
            $this->pauseForHost($party, 'player.host_account_needs_relink');

            return;
        } catch (ProviderTemporaryFailure $failure) {
            $this->retryLater($failure->retryAfterSeconds);

            return;
        } catch (ProviderUnavailableException) {
            $this->retryLater(null);

            return;
        }

        Cache::forget($this->failuresKey());
        $this->apply($party, $player, $coordinator, $state);
        $this->reschedule(self::delayFor($state));
    }

    public function failed(Throwable $failure): void
    {
        Log::warning('Playback poll failed', ['party' => $this->partyCode, 'exception' => $failure::class]);

        if ($this->isStale()) {
            return;
        }

        $this->reschedule(self::setting('backoff_cap_seconds'));
    }

    private function apply(Party $party, PollingPlayer $player, PlaybackCoordinator $coordinator, PlaybackState $state): void
    {
        $player->remember($state);
        $last = $player->lastSeenTrackId();

        if ($state->currentTrack === null) {
            if ($last !== null) {
                $player->markSeen(null);
                $coordinator->playbackEnded($party);
            }

            return;
        }

        if ($state->status !== PlaybackStatus::Playing) {
            return;
        }

        if ($state->currentTrack->providerTrackId === $last) {
            $coordinator->tick($party);

            return;
        }

        $coordinator->trackChanged($party, $state->currentTrack->providerTrackId);
        $player->markSeen($state->currentTrack->providerTrackId);
    }

    private function pauseForHost(Party $party, string $action): void
    {
        $this->stop();

        try {
            app(PauseParty::class)(null, $party, 'player', $action);
        } catch (InvalidPartyTransition) {
            return;
        }

        app(RecordPartyLogEntry::class)($party, $action, systemActor: 'player');
    }

    private function retryLater(?int $retryAfterSeconds): void
    {
        $failures = (int) Cache::get($this->failuresKey(), 0) + 1;
        Cache::put($this->failuresKey(), $failures, self::CHAIN_GRACE_SECONDS * 60);

        $this->reschedule(self::backoffDelay($failures, $retryAfterSeconds));
    }

    private function reschedule(int $delay): void
    {
        if (! $this->reschedule) {
            return;
        }

        Cache::put(self::chainKey($this->partyCode), $this->chainToken ?? true, $delay + self::CHAIN_GRACE_SECONDS);
        self::dispatch($this->partyCode, true, $this->chainToken)->delay($delay);
    }

    private function isStale(): bool
    {
        return $this->reschedule
            && $this->chainToken !== null
            && Cache::get(self::chainKey($this->partyCode)) !== $this->chainToken;
    }

    private function stop(): void
    {
        Cache::forget(self::chainKey($this->partyCode));
        Cache::forget($this->failuresKey());
    }

    private function failuresKey(): string
    {
        return "playback.poll.{$this->partyCode}.failures";
    }

    private static function chainKey(string $partyCode): string
    {
        return "playback.poll.{$partyCode}.chain";
    }

    private static function setting(string $key): int
    {
        return (int) config("musicparty.polling.{$key}");
    }
}

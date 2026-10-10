<?php

namespace App\Domain\Music\Jobs;

use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Models\Party;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppendToHistoryPlaylist implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function __construct(
        public readonly int $partyId,
        public readonly string $providerTrackId,
        public readonly string $providerId,
    ) {
        $this->onQueue('default');
    }

    public function handle(MusicProvider $provider, AuthorisesHost $accounts): void
    {
        $party = Party::query()->with('user')->find($this->partyId);

        if ($party === null || $party->history_playlist_id === null || ! $party->user instanceof User) {
            return;
        }

        $account = $accounts->linkedAccountFor($party->user, $this->providerId);

        if ($account === null) {
            return;
        }

        try {
            $provider->appendToPlaylist($party->history_playlist_id, [$this->providerTrackId], (string) $account->id);
        } catch (ProviderTemporaryFailure $failure) {
            if ($this->attempts() < $this->tries) {
                $this->release($failure->retryAfterSeconds ?? $this->backoff()[min($this->attempts() - 1, 3)]);

                return;
            }

            $this->logFailure($failure);
        } catch (Throwable $failure) {
            $this->logFailure($failure);
        }
    }

    public function failed(Throwable $failure): void
    {
        $this->logFailure($failure);
    }

    private function logFailure(Throwable $failure): void
    {
        Log::warning('Failed to append a play to the History Playlist', [
            'party_id' => $this->partyId,
            'provider' => $this->providerId,
            'exception' => $failure::class,
        ]);

        $party = Party::query()->find($this->partyId);

        if ($party !== null) {
            app(RecordPartyLogEntry::class)($party, 'playlist.history_append_failed', systemActor: 'history', details: ['provider' => $this->providerId, 'exception' => $failure::class]);
        }
    }
}

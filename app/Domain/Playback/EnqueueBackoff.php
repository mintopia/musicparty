<?php

namespace App\Domain\Playback;

use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\TrackRequest;
use Illuminate\Support\Facades\Cache;

class EnqueueBackoff
{
    private const int TTL_SECONDS = 86400;

    public function shouldAttempt(TrackRequest $request): bool
    {
        $state = $this->state($request);

        if ($state === null) {
            return true;
        }

        return ! $state['exhausted'] && $state['next_at'] <= now()->getTimestamp();
    }

    /**
     * @return array{attempt: int, retry_in: ?int}
     */
    public function recordFailure(TrackRequest $request): array
    {
        $attempt = ($this->state($request)['attempt'] ?? 0) + 1;
        $schedule = $this->schedule();
        $retryIn = $schedule[$attempt - 1] ?? null;

        Cache::put($this->key($request->id), [
            'attempt' => $attempt,
            'next_at' => now()->getTimestamp() + ($retryIn ?? 0),
            'exhausted' => $retryIn === null,
        ], self::TTL_SECONDS);

        return ['attempt' => $attempt, 'retry_in' => $retryIn];
    }

    public function clearForParty(Party $party): void
    {
        TrackRequest::query()
            ->where('party_id', $party->id)
            ->where('status', RequestStatus::UpNext)
            ->pluck('id')
            ->each(fn (int $id): bool => Cache::forget($this->key($id)));
    }

    /**
     * @return array{attempt: int, next_at: int, exhausted: bool}|null
     */
    private function state(TrackRequest $request): ?array
    {
        $state = Cache::get($this->key($request->id));

        if (! is_array($state)) {
            return null;
        }

        return [
            'attempt' => (int) ($state['attempt'] ?? 0),
            'next_at' => (int) ($state['next_at'] ?? 0),
            'exhausted' => (bool) ($state['exhausted'] ?? false),
        ];
    }

    /**
     * @return list<int>
     */
    private function schedule(): array
    {
        return array_values(array_map(intval(...), (array) config('musicparty.playback.enqueue_backoff', [5, 15, 30, 60, 120, 300])));
    }

    private function key(int $requestId): string
    {
        return "playback.enqueue-backoff.{$requestId}";
    }
}

<?php

namespace App\Domain\Queue;

use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\Cache;

class ReplayMarker
{
    private const int TTL_SECONDS = 30;

    public function set(Party $party, int $trackRequestId, string $providerTrackId): void
    {
        Cache::put($this->key($party), ['request' => $trackRequestId, 'track' => $providerTrackId], self::TTL_SECONDS);
    }

    /**
     * Returns the replayed Track Request id when the marker matches the observed Track, consuming the marker.
     */
    public function claim(Party $party, string $providerTrackId): ?int
    {
        $marker = Cache::get($this->key($party));

        if (! is_array($marker) || ($marker['track'] ?? null) !== $providerTrackId) {
            return null;
        }

        Cache::forget($this->key($party));

        return (int) $marker['request'];
    }

    private function key(Party $party): string
    {
        return "party:{$party->id}:replay-marker";
    }
}

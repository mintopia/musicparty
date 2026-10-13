<?php

namespace App\Services;

use App\Models\Party;
use App\Services\Music\Data\PlaybackState;
use Illuminate\Support\Facades\Cache;

class PlaybackStatus
{
    private const TTL_SECONDS = 86400;

    public function remember(Party $party, PlaybackState $state): void
    {
        Cache::put($this->key($party), $state, self::TTL_SECONDS);
    }

    public function recall(Party $party): ?PlaybackState
    {
        $state = Cache::get($this->key($party));

        return $state instanceof PlaybackState ? $state : null;
    }

    public function forParty(Party $party): object
    {
        $state = $this->recall($party);

        if ($state === null) {
            return (object) [
                'device' => null,
                'repeat' => null,
                'shuffle' => null,
                'active' => false,
                'isPlaying' => false,
                'progress' => null,
                'length' => null,
                'updatedAt' => now()->toIso8601String(),
            ];
        }

        $isCurrentSong = $party->song !== null && $state->trackId === $party->song->spotify_id;

        return (object) [
            'device' => $state->deviceId === null && $state->deviceName === null ? null : (object) [
                'id' => $state->deviceId,
                'name' => $state->deviceName,
                'type' => null,
                'volume' => null,
            ],
            'repeat' => $state->repeat,
            'shuffle' => $state->shuffle,
            'active' => $isCurrentSong,
            'isPlaying' => $state->isPlaying,
            'progress' => $isCurrentSong ? $state->progressMs : null,
            'length' => $isCurrentSong ? $party->song->length : null,
            'updatedAt' => $state->sampledAt->toIso8601String(),
        ];
    }

    private function key(Party $party): string
    {
        return "party.{$party->id}.playback";
    }
}

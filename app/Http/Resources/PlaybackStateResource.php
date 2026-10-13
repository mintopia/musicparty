<?php

namespace App\Http\Resources;

use App\Services\Music\Data\PlaybackState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlaybackState
 */
class PlaybackStateResource extends JsonResource
{
    /**
     * @return array{isPlaying: bool, trackId: string|null, linkedFromId: string|null, durationMs: int|null, progressMs: int, sampledAt: string, shuffle: bool, repeat: string, deviceId: string|null, deviceName: string|null, source: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'isPlaying' => $this->isPlaying,
            'trackId' => $this->trackId,
            'linkedFromId' => $this->linkedFromId,
            'durationMs' => $this->durationMs,
            'progressMs' => $this->progressMs,
            'sampledAt' => $this->sampledAt->toIso8601String(),
            'shuffle' => $this->shuffle,
            'repeat' => $this->repeat,
            'deviceId' => $this->deviceId,
            'deviceName' => $this->deviceName,
            'source' => $this->source->value,
        ];
    }
}

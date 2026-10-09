<?php

namespace App\Domain\Playback\Data;

use App\Domain\Playback\PlaybackStatus;
use Carbon\CarbonImmutable;

final readonly class PlaybackState
{
    public function __construct(
        public PlaybackStatus $status,
        public ?TrackReference $currentTrack = null,
        public int $positionMs = 0,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    public static function stopped(): self
    {
        return new self(PlaybackStatus::Stopped);
    }
}

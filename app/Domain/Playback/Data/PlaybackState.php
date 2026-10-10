<?php

namespace App\Domain\Playback\Data;

use App\Domain\Playback\PlaybackStatus;
use Carbon\CarbonImmutable;

final readonly class PlaybackState
{
    /**
     * @param  list<string>|null  $queuedTrackIds  Upcoming Tracks the Player reports, or null when it cannot say.
     */
    public function __construct(
        public PlaybackStatus $status,
        public ?TrackReference $currentTrack = null,
        public int $positionMs = 0,
        public ?CarbonImmutable $updatedAt = null,
        public ?int $durationMs = null,
        public ?array $queuedTrackIds = null,
    ) {}

    public static function stopped(): self
    {
        return new self(PlaybackStatus::Stopped);
    }
}

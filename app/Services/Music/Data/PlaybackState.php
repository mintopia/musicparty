<?php

namespace App\Services\Music\Data;

use Carbon\CarbonImmutable;

final readonly class PlaybackState
{
    public function __construct(
        public bool $isPlaying,
        public ?string $trackId,
        public ?string $linkedFromId,
        public ?int $durationMs,
        public int $progressMs,
        public CarbonImmutable $sampledAt,
        public bool $shuffle,
        public string $repeat,
        public ?string $deviceId,
        public ?string $deviceName,
        public PlaybackSource $source,
    ) {}

    public static function idle(PlaybackSource $source): self
    {
        return new self(false, null, null, null, 0, CarbonImmutable::now(), false, 'off', null, null, $source);
    }
}

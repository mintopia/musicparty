<?php

namespace App\Domain\Playback\Data;

use Carbon\CarbonImmutable;

final readonly class PlayerCommand
{
    public function __construct(
        public string $type,
        public ?string $providerTrackId,
        public CarbonImmutable $at,
        public ?int $value = null,
    ) {}
}

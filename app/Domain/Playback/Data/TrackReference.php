<?php

namespace App\Domain\Playback\Data;

final readonly class TrackReference
{
    public function __construct(
        public string $providerId,
        public string $providerTrackId,
    ) {}
}

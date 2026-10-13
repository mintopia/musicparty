<?php

namespace App\Services\Music\Data;

final readonly class Artist
{
    public function __construct(
        public ArtistRef $ref,
        public ?string $imageUrl,
    ) {}
}

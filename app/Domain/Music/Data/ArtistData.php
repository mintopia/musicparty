<?php

namespace App\Domain\Music\Data;

final readonly class ArtistData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}

<?php

namespace App\Services\Music\Data;

final readonly class ArtistRef
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}

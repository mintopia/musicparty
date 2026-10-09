<?php

namespace App\Domain\Music\Data;

final readonly class PlaylistData
{
    public function __construct(
        public string $id,
        public string $name,
        public int $trackCount = 0,
    ) {}
}

<?php

namespace App\Services\Music\Data;

final readonly class Playlist
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $ownerName,
        public int $trackCount,
    ) {}
}

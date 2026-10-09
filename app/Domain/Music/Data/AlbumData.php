<?php

namespace App\Domain\Music\Data;

final readonly class AlbumData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}

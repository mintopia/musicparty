<?php

namespace App\Services\Music\Data;

final readonly class AlbumRef
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $imageUrl,
    ) {}
}

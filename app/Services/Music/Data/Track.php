<?php

namespace App\Services\Music\Data;

final readonly class Track
{
    /**
     * @param  list<ArtistRef>  $artists
     */
    public function __construct(
        public string $providerId,
        public string $id,
        public string $name,
        public int $durationMs,
        public bool $explicit,
        public bool $isPlayable,
        public AlbumRef $album,
        public array $artists,
        public ?string $linkedFromId = null,
    ) {}
}

<?php

namespace App\Services\Music\Data;

final readonly class AlbumSummary
{
    /**
     * @param  list<ArtistRef>  $artists
     */
    public function __construct(
        public AlbumRef $ref,
        public string $albumType,
        public array $artists,
        public ?string $releaseYear,
    ) {}
}

<?php

namespace App\Services\Music\Data;

final readonly class Album
{
    /**
     * @param  list<ArtistRef>  $artists
     * @param  list<Track>  $tracks
     */
    public function __construct(
        public AlbumRef $ref,
        public array $artists,
        public ?string $releaseDate,
        public int $totalTracks,
        public array $tracks,
    ) {}
}

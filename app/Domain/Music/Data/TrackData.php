<?php

namespace App\Domain\Music\Data;

final readonly class TrackData
{
    /**
     * @param  list<ArtistData>  $artists
     * @param  list<string>  $coverArtUrls
     */
    public function __construct(
        public string $providerId,
        public string $providerTrackId,
        public string $name,
        public array $artists,
        public AlbumData $album,
        public int $durationMs,
        public bool $explicit = false,
        public ?string $isrc = null,
        public array $coverArtUrls = [],
        public bool $playableInMarket = true,
    ) {}
}

<?php

namespace App\Services\Music\Data;

final readonly class SearchOverview
{
    /**
     * @param  Page<Track>  $tracks
     * @param  Page<AlbumSummary>  $albums
     * @param  Page<Artist>  $artists
     */
    public function __construct(
        public Page $tracks,
        public Page $albums,
        public Page $artists,
    ) {}
}

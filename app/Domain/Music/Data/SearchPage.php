<?php

namespace App\Domain\Music\Data;

final readonly class SearchPage
{
    /**
     * @param  list<TrackData>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $offset,
        public int $limit,
    ) {}
}

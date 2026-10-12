<?php

namespace App\Domain\Queue\Data;

final readonly class SearchResults
{
    /**
     * @param  list<SearchHit>  $hits
     */
    public function __construct(
        public array $hits,
        public bool $hasMore,
    ) {}
}

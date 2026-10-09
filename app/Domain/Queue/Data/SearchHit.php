<?php

namespace App\Domain\Queue\Data;

use App\Domain\Music\Data\TrackData;

final readonly class SearchHit
{
    public function __construct(
        public TrackData $track,
        public bool $queued,
    ) {}
}

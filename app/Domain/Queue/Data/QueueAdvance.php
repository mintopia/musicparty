<?php

namespace App\Domain\Queue\Data;

use App\Models\TrackRequest;

final readonly class QueueAdvance
{
    public function __construct(
        public ?TrackRequest $playing,
        public bool $duplicate = false,
        public bool $unexpectedTrack = false,
    ) {}
}

<?php

namespace App\Domain\Queue\Data;

use App\Models\TrackRequest;

final readonly class RequestOutcome
{
    public function __construct(
        public TrackRequest $request,
        public bool $created,
    ) {}
}

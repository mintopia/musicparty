<?php

namespace App\Domain\Queue\Data;

use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Support\Collection;

final readonly class SongDetail
{
    /**
     * @param  Collection<int, TrackRequest>  $appearances
     */
    public function __construct(
        public TrackRequest $request,
        public Collection $appearances,
        public bool $canModerate,
    ) {}
}

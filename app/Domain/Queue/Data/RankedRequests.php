<?php

namespace App\Domain\Queue\Data;

use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Database\Eloquent\Collection;

readonly class RankedRequests
{
    /**
     * @param  Collection<int, TrackRequest>  $requests
     * @param  array<int, array<string, array{name: string, value: int}>>  $adjustments
     */
    public function __construct(public Collection $requests, public array $adjustments) {}
}

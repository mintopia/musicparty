<?php

namespace Tests\Fixtures\Architecture;

use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\TrackRequest;

class CrossContextQueryWriter
{
    public function write(TrackRequest $track): void
    {
        Party::query()->where('id', 1)->update(['name' => 'x']);
        $track->votes()->delete();
    }
}

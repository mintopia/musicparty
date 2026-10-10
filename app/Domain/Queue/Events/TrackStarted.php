<?php

namespace App\Domain\Queue\Events;

use App\Domain\Mod\Contracts\PartyScopedEvent;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Foundation\Events\Dispatchable;

class TrackStarted implements PartyScopedEvent
{
    use Dispatchable;

    public function __construct(
        public Party $party,
        public TrackRequest $request,
    ) {}

    public function party(): Party
    {
        return $this->party;
    }
}

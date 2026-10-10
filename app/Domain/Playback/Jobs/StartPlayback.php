<?php

namespace App\Domain\Playback\Jobs;

use App\Domain\Party\Models\Party;
use App\Domain\Playback\PlaybackCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class StartPlayback implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public string $partyCode)
    {
        $this->onQueue('player');
    }

    public function handle(PlaybackCoordinator $coordinator): void
    {
        $party = Party::findByCode($this->partyCode);

        if ($party !== null) {
            $coordinator->startIfIdle($party);
        }
    }
}

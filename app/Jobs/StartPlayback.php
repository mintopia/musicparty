<?php

namespace App\Jobs;

use App\Domain\Playback\PlaybackCoordinator;
use App\Models\Party;
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
        $this->onQueue('partyupdates');
    }

    public function handle(PlaybackCoordinator $coordinator): void
    {
        $party = Party::findByCode($this->partyCode);

        if ($party !== null) {
            $coordinator->startIfIdle($party);
        }
    }
}

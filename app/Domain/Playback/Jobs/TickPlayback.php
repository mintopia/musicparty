<?php

namespace App\Domain\Playback\Jobs;

use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\PlaybackCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class TickPlayback implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('player');
    }

    public function handle(PlaybackCoordinator $coordinator): void
    {
        Party::query()->where('state', PartyState::Live)->whereNotNull('player_kind')->each(function (Party $party) use ($coordinator): void {
            try {
                $coordinator->tick($party);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}

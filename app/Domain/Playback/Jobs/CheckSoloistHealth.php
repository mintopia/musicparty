<?php

namespace App\Domain\Playback\Jobs;

use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Players\SoloistPlayer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class CheckSoloistHealth implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('player');
    }

    public function handle(PartyPlayers $players): void
    {
        Party::query()->where('state', PartyState::Live)->where('player_kind', SoloistPlayer::KIND)->each(function (Party $party) use ($players): void {
            $player = $players->for($party);

            if (! $player instanceof SoloistPlayer) {
                return;
            }

            try {
                $player->checkHealth();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}

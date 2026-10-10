<?php

namespace App\Domain\Playback\Jobs;

use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class TickPlayback implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('player');
    }

    public function handle(): void
    {
        Party::query()
            ->where('state', PartyState::Live)
            ->whereNotNull('player_kind')
            ->each(fn (Party $party) => TickParty::dispatch($party->code));
    }
}

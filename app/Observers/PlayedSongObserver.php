<?php

namespace App\Observers;

use App\Jobs\RefreshPartyVibeSummary;
use App\Models\PlayedSong;

class PlayedSongObserver
{
    public function saved(PlayedSong $played): void
    {
        RefreshPartyVibeSummary::debounced($played->party);
    }
}

<?php

namespace App\Observers;

use App\Jobs\RefreshPartyVibeSummary;
use App\Models\Vote;

class VoteObserver
{
    public function saved(Vote $vote): void
    {
        $vote->upcomingSong->updateScore();
        RefreshPartyVibeSummary::debounced($vote->upcomingSong->party);
    }

    public function deleted(Vote $vote): void
    {
        $vote->upcomingSong->updateScore();
        RefreshPartyVibeSummary::debounced($vote->upcomingSong->party);
    }
}

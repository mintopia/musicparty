<?php

namespace App\Observers;

use App\Models\Vote;

class VoteObserver
{
    public function saved(Vote $vote): void
    {
        $vote->upcomingSong->updateScore();
    }

    public function deleted(Vote $vote): void
    {
        $vote->upcomingSong->updateScore();
    }
}

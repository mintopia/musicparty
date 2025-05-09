<?php

namespace App\Observers;

use App\Models\SongRating;

class SongRatingObserver
{
    public function saved(SongRating $rating): void
    {
        $rating->song->updateRating();
        $rating->song->party->pushUpdate();
    }

    public function deleted(SongRating $rating): void
    {
        $rating->song->updateRating();
        $rating->song->party->pushUpdate();
    }
}

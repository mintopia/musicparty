<?php

namespace App\Observers;

use App\Events\UpcomingSong\RemovedEvent;
use App\Events\UpcomingSong\UpdatedEvent;
use App\Models\UpcomingSong;

class UpcomingSongObserver
{
    public function created(UpcomingSong $upcomingSong): void
    {
        UpdatedEvent::dispatch($upcomingSong);
    }

    public function updated(UpcomingSong $upcomingSong): void
    {
        if ($upcomingSong->queued_at) {
            RemovedEvent::dispatch($upcomingSong->party, $upcomingSong->id);
        } else {
            UpdatedEvent::dispatch($upcomingSong);
        }
    }

    public function deleted(UpcomingSong $upcomingSong): void
    {
        RemovedEvent::dispatch($upcomingSong->party, $upcomingSong->id);
    }
}

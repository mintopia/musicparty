<?php

namespace App\Observers;

use App\Events\UpcomingSong\RemovedEvent;
use App\Events\UpcomingSong\UpdatedEvent;
use App\Models\UpcomingSong;

class UpcomingSongObserver
{
    public function created(UpcomingSong $upcomingSong)
    {
        UpdatedEvent::dispatch($upcomingSong->party->code, $upcomingSong->id);
    }

    public function updated(UpcomingSong $upcomingSong)
    {
        if ($upcomingSong->queued_at) {
            RemovedEvent::dispatch($upcomingSong->party->code, $upcomingSong->id);
        } else {
            UpdatedEvent::dispatch($upcomingSong->party->code, $upcomingSong->id);
        }
    }

    public function deleted(UpcomingSong $upcomingSong)
    {
        RemovedEvent::dispatch($upcomingSong->party->code, $upcomingSong->id);
    }
}

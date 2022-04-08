<?php

namespace App\Observers;

use App\Events\UpcomingSong\RemovedEvent;
use App\Events\UpcomingSong\UpdatedEvent;
use App\Models\UpcomingSong;

class UpcomingSongObserver
{
    /**
     * Handle the UpcomingSong "created" event.
     *
     * @param  \App\Models\UpcomingSong  $upcomingSong
     * @return void
     */
    public function created(UpcomingSong $upcomingSong)
    {
        UpdatedEvent::dispatch($upcomingSong);
    }

    /**
     * Handle the UpcomingSong "updated" event.
     *
     * @param  \App\Models\UpcomingSong  $upcomingSong
     * @return void
     */
    public function updated(UpcomingSong $upcomingSong)
    {
        if ($upcomingSong->queued_at) {
            RemovedEvent::dispatch($upcomingSong->party, $upcomingSong->id);
        } else {
            UpdatedEvent::dispatch($upcomingSong);
        }
    }

    /**
     * Handle the UpcomingSong "deleted" event.
     *
     * @param  \App\Models\UpcomingSong  $upcomingSong
     * @return void
     */
    public function deleted(UpcomingSong $upcomingSong)
    {
        RemovedEvent::dispatch($upcomingSong->party, $upcomingSong->id);
    }
}

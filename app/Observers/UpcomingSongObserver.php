<?php

namespace App\Observers;

use App\Events\UpcomingSong\RemovedEvent;
use App\Events\UpcomingSong\UpdatedEvent;
use App\Models\UpcomingSong;
use App\Services\Queue\QueueListing;

class UpcomingSongObserver
{
    public function created(UpcomingSong $upcomingSong): void
    {
        QueueListing::forget($upcomingSong->party_id);
        if ($upcomingSong->flagged_at === null) {
            UpdatedEvent::dispatch($upcomingSong);
        }
    }

    public function updated(UpcomingSong $upcomingSong): void
    {
        QueueListing::forget($upcomingSong->party_id);
        if ($upcomingSong->queued_at) {
            RemovedEvent::dispatch($upcomingSong->party, $upcomingSong->id, played: true);
        } elseif ($upcomingSong->flagged_at === null) {
            UpdatedEvent::dispatch($upcomingSong);
        }
    }

    public function deleted(UpcomingSong $upcomingSong): void
    {
        QueueListing::forget($upcomingSong->party_id);
        RemovedEvent::dispatch($upcomingSong->party, $upcomingSong->id);
    }
}

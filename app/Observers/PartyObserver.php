<?php

namespace App\Observers;

use App\Events\Party\UpdatedEvent;
use App\Events\Party\UpdatedEventOwner;
use App\Models\Party;

class PartyObserver
{
    public function creating(Party $party)
    {
        $party->setCode();
        $party->createPlaylist();
    }

    /**
     * Handle the Party "created" event.
     *
     * @param  \App\Models\Party  $party
     * @return void
     */
    public function created(Party $party)
    {
        // Publish Update
    }

    /**
     * Handle the Party "updated" event.
     *
     * @param  \App\Models\Party  $party
     * @return void
     */
    public function updated(Party $party)
    {
        UpdatedEvent::dispatch($party);
        UpdatedEventOwner::dispatch($party);
    }

    /**
     * Handle the Party "deleted" event.
     *
     * @param  \App\Models\Party  $party
     * @return void
     */
    public function deleted(Party $party)
    {
        // Publish Update
    }

    /**
     * Handle the Party "restored" event.
     *
     * @param  \App\Models\Party  $party
     * @return void
     */
    public function restored(Party $party)
    {
        //
    }

    /**
     * Handle the Party "force deleted" event.
     *
     * @param  \App\Models\Party  $party
     * @return void
     */
    public function forceDeleted(Party $party)
    {
        //
    }
}

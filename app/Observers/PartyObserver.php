<?php

namespace App\Observers;

use App\Models\Party;
use Illuminate\Support\Str;

class PartyObserver
{
    public function creating(Party $party): void
    {
        if (!$party->code) {
            $party->code = Str::upper(Str::password(4, numbers: false, symbols: false));
        }
    }
    /**
     * Handle the Party "created" event.
     */
    public function created(Party $party): void
    {
        $party->updateState();
    }

    public function saved(Party $party): void
    {
        $party->updatePlaylist();
    }

    public function saving(Party $party): void
    {
        $party->updateDeviceName();
    }

    /**
     * Handle the Party "updated" event.
     */
    public function updated(Party $party): void
    {
        //
    }

    /**
     * Handle the Party "deleted" event.
     */
    public function deleted(Party $party): void
    {
        //
    }

    /**
     * Handle the Party "restored" event.
     */
    public function restored(Party $party): void
    {
        //
    }

    /**
     * Handle the Party "force deleted" event.
     */
    public function forceDeleted(Party $party): void
    {
        //
    }
}

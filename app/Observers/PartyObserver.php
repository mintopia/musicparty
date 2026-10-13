<?php

namespace App\Observers;

use App\Jobs\RefreshFallbackPlaylist;
use App\Models\Party;
use Illuminate\Support\Str;

class PartyObserver
{
    public function creating(Party $party): void
    {
        if (! $party->code) {
            $party->code = Str::upper(Str::password(4, numbers: false, symbols: false));
        }
    }

    public function saved(Party $party): void
    {
        if ($party->isDirty('backup_playlist_id')) {
            RefreshFallbackPlaylist::dispatch($party);
        }
    }

    /**
     * Handle the Party "updated" event.
     */
    public function updated(Party $party): void
    {
        if ($party->wasChanged(Party::RENDERED_ATTRIBUTES)) {
            $party->broadcastRenderedChange();
        }
    }
}

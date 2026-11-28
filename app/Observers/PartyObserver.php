<?php

namespace App\Observers;

use App\Jobs\RefreshFallbackPlaylist;
use App\Models\Party;
use App\Services\ModDataStore;
use App\Services\PartyCodeGenerator;

class PartyObserver
{
    public function creating(Party $party): void
    {
        if (! $party->code) {
            $party->code = app(PartyCodeGenerator::class)->unused();
        }
    }

    public function saving(Party $party): void
    {
        if ($party->exists && $party->isDirty('user_id')) {
            $party->context_playlist_id = null;
        }
    }

    public function saved(Party $party): void
    {
        if ($party->isDirty('backup_playlist_id')) {
            RefreshFallbackPlaylist::dispatch($party);
        }
    }

    public function deleting(Party $party): void
    {
        $store = app(ModDataStore::class);
        $party->members->each(fn ($member) => $store->purge($member));
        $store->purge($party);
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

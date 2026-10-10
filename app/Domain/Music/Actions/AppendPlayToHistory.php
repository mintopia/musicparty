<?php

namespace App\Domain\Music\Actions;

use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Jobs\AppendToHistoryPlaylist;
use App\Domain\Party\Models\Party;

class AppendPlayToHistory
{
    public function __invoke(Party $party, string $providerTrackId, MusicProvider $provider): void
    {
        if ($party->history_playlist_id === null) {
            return;
        }

        AppendToHistoryPlaylist::dispatch($party->id, $providerTrackId, $provider->id());
    }
}

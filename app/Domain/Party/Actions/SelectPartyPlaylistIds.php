<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Models\Party;

readonly class SelectPartyPlaylistIds
{
    public function __invoke(Party $party, ?string $fallbackPlaylistId, ?string $historyPlaylistId): Party
    {
        $party->forceFill([
            'fallback_playlist_id' => $fallbackPlaylistId,
            'history_playlist_id' => $historyPlaylistId,
        ])->save();

        return $party;
    }
}

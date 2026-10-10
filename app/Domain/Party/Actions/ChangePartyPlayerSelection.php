<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Models\Party;

readonly class ChangePartyPlayerSelection
{
    public function __invoke(Party $party, string $playerKind, string $musicProvider): Party
    {
        $party->forceFill(['player_kind' => $playerKind, 'music_provider' => $musicProvider])->save();

        return $party;
    }
}

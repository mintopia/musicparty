<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Models\Party;

readonly class StorePartyTheme
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Party $party, array $attributes): Party
    {
        $party->forceFill($attributes)->saveQuietly();

        return $party;
    }
}

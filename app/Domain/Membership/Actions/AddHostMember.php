<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;

readonly class AddHostMember
{
    public function __invoke(Party $party, User $host): PartyMember
    {
        return PartyMember::query()->forceCreate([
            'party_id' => $party->id,
            'user_id' => $host->id,
            'role' => PartyRole::Host,
        ]);
    }
}

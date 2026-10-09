<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\PartyRole;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class JoinParty
{
    public function __invoke(User $user, Party $party): PartyMember
    {
        $existing = $party->memberFor($user);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return PartyMember::query()->forceCreate([
                'party_id' => $party->id,
                'user_id' => $user->id,
                'role' => PartyRole::Guest,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            return $party->memberFor($user) ?? throw $exception;
        }
    }
}

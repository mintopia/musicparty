<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
use Illuminate\Database\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class JoinParty
{
    public function __invoke(User $user, Party $party): PartyMember
    {
        if (! $user->hasCompletedSignup()) {
            throw new AccessDeniedHttpException('Finish signing up before joining a party.');
        }

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

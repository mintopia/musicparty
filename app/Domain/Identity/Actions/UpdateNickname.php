<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

readonly class UpdateNickname
{
    public function __invoke(User $user, string $nickname): User
    {
        $user->forceFill(['nickname' => $nickname])->saveClaimingNickname();

        return $user;
    }
}

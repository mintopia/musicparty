<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

readonly class SetUserSuspended
{
    public function __invoke(User $user, bool $suspended): User
    {
        $user->forceFill(['suspended' => $suspended])->save();

        return $user;
    }
}

<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

class CompleteSignup
{
    public function __invoke(User $user, string $nickname): User
    {
        $user->nickname = $nickname;
        $user->terms_agreed_at = now();
        $user->first_login = false;
        $user->save();

        return $user;
    }
}

<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

readonly class DeleteUser
{
    public function __invoke(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}

<?php

namespace App\Policies;

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;

class LinkedAccountPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LinkedAccount $linkedAccount): bool
    {
        return $user->id === $linkedAccount->user_id;
    }
}

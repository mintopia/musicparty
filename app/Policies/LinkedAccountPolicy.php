<?php

namespace App\Policies;

use App\Models\LinkedAccount;
use App\Models\User;

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

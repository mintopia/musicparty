<?php

namespace App\Observers;

use App\Domain\Admin\Models\Role;
use App\Domain\Identity\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $count = User::count();

        // Assign the admin role to the first user
        $role = $count === 1 ? Role::whereCode('admin')->first() : null;
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
            $user->save();
        }
    }
}

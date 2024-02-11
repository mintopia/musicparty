<?php

namespace App\Observers;

use App\Models\Role;
use App\Models\Seat;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $count = User::count();

        // Assign the admin role to the first user
        if ($count === 1) {
            $role = Role::whereCode('admin')->first();
            $user->roles()->attach($role);
            $user->save();
        }
    }
}

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

    public function saved(User $user): void
    {
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}

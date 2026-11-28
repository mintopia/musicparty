<?php

namespace App\Observers;

use App\Models\Role;
use App\Models\User;
use App\Services\ModDataStore;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        if (User::query()->limit(2)->count() === 1) {
            $user->roles()->attach(Role::whereCode('admin')->first());
        }
    }

    public function deleting(User $user): void
    {
        $store = app(ModDataStore::class);
        $user->partyMembers->each(fn ($member) => $store->purge($member));
        $store->purge($user);
    }
}

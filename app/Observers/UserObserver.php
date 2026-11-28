<?php

namespace App\Observers;

use App\Models\Role;
use App\Models\User;
use App\Services\ModDataStore;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $admin = Role::whereCode('admin')->first();
        if ($admin === null) {
            return;
        }

        Cache::lock('first-admin', 10)->block(5, function () use ($user, $admin): void {
            if ((int) User::query()->min('id') === $user->id && ! $admin->users()->exists()) {
                $user->grantRole($admin);
            }
        });
    }

    public function deleting(User $user): void
    {
        app(ModDataStore::class)->purgeAll(collect([...$user->partyMembers->all(), $user]));
    }
}

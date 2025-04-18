<?php

namespace App\Policies;

use App\Models\UpcomingSong;
use App\Models\User;

class UpcomingSongPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UpcomingSong $upcomingSong): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UpcomingSong $upcomingSong): bool
    {
        return $upcomingSong->party->canBeManagedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UpcomingSong $upcomingSong): bool
    {
        return $this->update($user, $upcomingSong);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UpcomingSong $upcomingSong): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UpcomingSong $upcomingSong): bool
    {
        return $this->restore($user, $upcomingSong);
    }
}

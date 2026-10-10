<?php

namespace App\Policies;

use App\Domain\Party\PartyRole;
use App\Models\Party;
use App\Models\User;

class PartyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Party $party): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('create-party') || $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Party $party): bool
    {
        return $party->canBeManagedBy($user);
    }

    public function transition(User $user, Party $party): bool
    {
        return $party->canBeManagedBy($user);
    }

    public function control(User $user, Party $party): bool
    {
        return $party->canBeManagedBy($user);
    }

    public function viewLog(User $user, Party $party): bool
    {
        $member = $party->memberFor($user);

        return $member !== null && ! $member->banned
            && in_array($member->role, [PartyRole::Host, PartyRole::Moderator], true);
    }

    public function viewMembers(User $user, Party $party): bool
    {
        $member = $party->memberFor($user);

        return $member !== null && ! $member->banned;
    }

    public function manageRoles(User $user, Party $party): bool
    {
        $member = $party->memberFor($user);

        return $member !== null && ! $member->banned && $member->role === PartyRole::Host;
    }

    public function moderate(User $user, Party $party): bool
    {
        return $this->viewLog($user, $party);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Party $party): bool
    {
        return $this->update($user, $party);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Party $party): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Party $party): bool
    {
        return $user->hasRole('admin');
    }
}

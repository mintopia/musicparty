<?php

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;

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
        return $user->hasCompletedSignup() && ($user->hasRole('create-party') || $user->hasRole('admin'));
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

    public function manageMods(User $user, Party $party): bool
    {
        return $party->canBeManagedBy($user);
    }

    public function control(User $user, Party $party): bool
    {
        return $party->canBeManagedBy($user);
    }

    public function export(User $user, Party $party): bool
    {
        return $party->canBeManagedBy($user) || $user->hasRole('admin');
    }

    public function viewLog(User $user, Party $party): bool
    {
        $member = $party->memberFor($user);

        return $member !== null && ! $member->banned
            && $member->role->isStaff();
    }

    public function viewMembers(User $user, Party $party): bool
    {
        $member = $party->memberFor($user);

        return $member !== null && ! $member->banned;
    }

    public function manageRoles(User $user, Party $party): bool
    {
        $member = $party->memberFor($user);

        return $member !== null && ! $member->banned && $member->role->isHost();
    }

    public function host(User $user, Party $party): bool
    {
        return $user->id === $party->user_id;
    }

    public function moderateMember(User $user, Party $party, PartyMember $target): bool
    {
        $member = $party->memberFor($user);

        return $this->moderate($user, $party)
            && ! ($member?->role->isModerator() && $target->role->isModerator());
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

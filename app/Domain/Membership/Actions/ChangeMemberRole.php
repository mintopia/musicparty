<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Exceptions\MembershipActionRefused;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

readonly class ChangeMemberRole
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * @throws MembershipActionRefused
     */
    public function __invoke(User $actor, Party $party, PartyMember $target, PartyRole $role): PartyMember
    {
        return DB::transaction(function () use ($actor, $party, $target, $role): PartyMember {
            if (Gate::forUser($actor)->denies('manageRoles', $party)) {
                throw MembershipActionRefused::onlyHostChangesRoles();
            }

            $target = PartyMember::query()
                ->whereBelongsTo($party)
                ->whereKey($target->id)
                ->lockForUpdate()
                ->with('user')
                ->firstOrFail();

            if ($target->role->isHost()) {
                throw MembershipActionRefused::hostRoleLocked();
            }

            if ($role->isHost()) {
                throw MembershipActionRefused::hostRoleNotAssignable();
            }

            if ($target->role === $role) {
                return $target;
            }

            $old = $target->role;
            $target->forceFill(['role' => $role])->save();

            ($this->record)($party, 'member.role_changed', $actor, $target->holder()->nickname, [
                'user_id' => $target->user_id,
                'old' => $old->value,
                'new' => $role->value,
            ]);

            return $target;
        });
    }
}

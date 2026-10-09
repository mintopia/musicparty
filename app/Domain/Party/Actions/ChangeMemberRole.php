<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Exceptions\MembershipActionRefused;
use App\Domain\Party\PartyRole;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class ChangeMemberRole
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * @throws MembershipActionRefused
     */
    public function __invoke(User $actor, Party $party, PartyMember $target, PartyRole $role): PartyMember
    {
        return DB::transaction(function () use ($actor, $party, $target, $role): PartyMember {
            $actingMember = $party->memberFor($actor);

            if ($actingMember === null || $actingMember->banned || $actingMember->role !== PartyRole::Host) {
                throw MembershipActionRefused::onlyHostChangesRoles();
            }

            $target = PartyMember::query()
                ->whereBelongsTo($party)
                ->whereKey($target->id)
                ->lockForUpdate()
                ->with('user')
                ->firstOrFail();

            if ($target->role === PartyRole::Host) {
                throw MembershipActionRefused::hostRoleLocked();
            }

            if ($role === PartyRole::Host) {
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

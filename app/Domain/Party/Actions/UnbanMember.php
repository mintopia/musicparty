<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Exceptions\MembershipActionRefused;
use App\Domain\Party\PartyRole;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class UnbanMember
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * @throws MembershipActionRefused
     */
    public function __invoke(User $actor, Party $party, PartyMember $target): PartyMember
    {
        return DB::transaction(function () use ($actor, $party, $target): PartyMember {
            $actingMember = $party->memberFor($actor);

            if ($actingMember === null || $actingMember->banned || ! in_array($actingMember->role, [PartyRole::Host, PartyRole::Moderator], true)) {
                throw MembershipActionRefused::notAllowed();
            }

            $target = PartyMember::query()
                ->whereBelongsTo($party)
                ->whereKey($target->id)
                ->lockForUpdate()
                ->with('user')
                ->firstOrFail();

            if ($actingMember->role === PartyRole::Moderator && $target->role === PartyRole::Moderator) {
                throw MembershipActionRefused::cannotActOnModerator();
            }

            if (! $target->banned) {
                return $target;
            }

            $target->forceFill(['banned' => false])->save();

            ($this->record)($party, 'member.unbanned', $actor, $target->holder()->nickname, [
                'user_id' => $target->user_id,
                'role' => $target->role->value,
            ]);

            return $target;
        });
    }
}

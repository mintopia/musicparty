<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Exceptions\MembershipActionRefused;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\DB;

readonly class BanMember
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

            if ($target->role === PartyRole::Host) {
                throw MembershipActionRefused::cannotBanHost();
            }

            if ($target->is($actingMember)) {
                throw MembershipActionRefused::cannotBanSelf();
            }

            if ($actingMember->role === PartyRole::Moderator && $target->role === PartyRole::Moderator) {
                throw MembershipActionRefused::cannotActOnModerator();
            }

            if ($target->banned) {
                return $target;
            }

            $target->forceFill(['banned' => true])->save();

            ($this->record)($party, 'member.banned', $actor, $target->holder()->nickname, [
                'user_id' => $target->user_id,
                'role' => $target->role->value,
            ]);

            return $target;
        });
    }
}

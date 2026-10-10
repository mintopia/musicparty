<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Exceptions\MembershipActionRefused;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

readonly class BanMember
{
    public function __construct(private RecordPartyLogEntry $record) {}

    /**
     * @throws MembershipActionRefused
     */
    public function __invoke(User $actor, Party $party, PartyMember $target): PartyMember
    {
        return DB::transaction(function () use ($actor, $party, $target): PartyMember {
            $gate = Gate::forUser($actor);

            if ($gate->denies('moderate', $party)) {
                throw MembershipActionRefused::notAllowed();
            }

            $target = PartyMember::query()
                ->whereBelongsTo($party)
                ->whereKey($target->id)
                ->lockForUpdate()
                ->with('user')
                ->firstOrFail();

            if ($target->role->isHost()) {
                throw MembershipActionRefused::cannotBanHost();
            }

            if ($target->user_id === $actor->id) {
                throw MembershipActionRefused::cannotBanSelf();
            }

            if ($gate->denies('moderateMember', [$party, $target])) {
                throw MembershipActionRefused::cannotActOnModerator();
            }

            if ($target->banned) {
                return $target;
            }

            $target->forceFill(['banned' => true])->save();

            $party->forgetMemberCache();

            ($this->record)($party, 'member.banned', $actor, $target->holder()->nickname, [
                'user_id' => $target->user_id,
                'role' => $target->role->value,
            ]);

            return $target;
        });
    }
}

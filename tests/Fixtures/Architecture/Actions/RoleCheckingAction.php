<?php

namespace Tests\Fixtures\Architecture\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;

class RoleCheckingAction
{
    public function __invoke(PartyMember $member): bool
    {
        return in_array($member->role, [PartyRole::Host, PartyRole::Moderator], true);
    }
}

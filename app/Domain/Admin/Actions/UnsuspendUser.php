<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Actions\SetUserSuspended;
use App\Domain\Identity\Models\User;

class UnsuspendUser
{
    public function __construct(private readonly RecordAdminAudit $audit, private readonly SetUserSuspended $setSuspended) {}

    public function handle(User $admin, User $user): User
    {
        if (! $user->suspended) {
            return $user;
        }

        ($this->setSuspended)($user, false);
        $this->audit->handle($admin, 'user.unsuspended', $user);

        return $user;
    }
}

<?php

namespace App\Domain\Admin\Actions;

use App\Models\User;

class UnsuspendUser
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, User $user): User
    {
        if (! $user->suspended) {
            return $user;
        }

        $user->forceFill(['suspended' => false])->save();
        $this->audit->handle($admin, 'user.unsuspended', $user);

        return $user;
    }
}

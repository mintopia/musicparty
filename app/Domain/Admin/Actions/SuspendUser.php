<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

class SuspendUser
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, User $user): User
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['admin' => 'You cannot suspend yourself.']);
        }

        if ($user->suspended) {
            return $user;
        }

        $user->forceFill(['suspended' => true])->save();
        $user->tokens()->delete();
        $this->audit->handle($admin, 'user.suspended', $user);

        return $user;
    }
}

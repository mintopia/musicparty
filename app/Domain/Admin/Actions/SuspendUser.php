<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Actions\SetUserSuspended;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

class SuspendUser
{
    public function __construct(private readonly RecordAdminAudit $audit, private readonly SetUserSuspended $setSuspended) {}

    public function handle(User $admin, User $user): User
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['admin' => 'You cannot suspend yourself.']);
        }

        if ($user->suspended) {
            return $user;
        }

        ($this->setSuspended)($user, true);
        $user->tokens()->delete();
        $this->audit->handle($admin, 'user.suspended', $user);

        return $user;
    }
}

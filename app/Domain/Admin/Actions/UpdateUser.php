<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\AdminRole;
use App\Domain\Identity\Actions\UpdateUserProfile;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    public function __construct(
        private readonly RecordAdminAudit $audit,
        private readonly UpdateUserProfile $updateProfile,
        private readonly GrantRole $grantRole,
        private readonly RevokeRole $revokeRole,
        private readonly SuspendUser $suspendUser,
        private readonly UnsuspendUser $unsuspendUser,
    ) {}

    /**
     * @param  array<int, string>  $roles
     */
    public function handle(User $admin, User $user, string $nickname, array $roles, bool $agreedToTerms, bool $completedSignup, bool $suspended): User
    {
        return DB::transaction(function () use ($admin, $user, $nickname, $roles, $agreedToTerms, $completedSignup, $suspended): User {
            $before = ['nickname' => $user->nickname, 'terms' => $user->terms_agreed_at !== null, 'signup' => ! $user->first_login];
            ($this->updateProfile)($user, $nickname, $agreedToTerms, $completedSignup);
            $after = ['nickname' => $user->nickname, 'terms' => $user->terms_agreed_at !== null, 'signup' => ! $user->first_login];

            if ($before !== $after) {
                $this->audit->handle($admin, 'user.updated', $user, ['nickname' => $user->nickname]);
            }

            foreach (AdminRole::cases() as $role) {
                if (in_array($role->value, $roles, true)) {
                    $this->grantRole->handle($admin, $user, $role->value);
                } else {
                    $this->revokeRole->handle($admin, $user, $role->value);
                }
            }

            if ($suspended) {
                $this->suspendUser->handle($admin, $user);
            } else {
                $this->unsuspendUser->handle($admin, $user);
            }

            return $user;
        });
    }
}

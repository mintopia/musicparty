<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\AdminRole;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeRole
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, User $user, string $role): User
    {
        $adminRole = AdminRole::tryFrom($role)
            ?? throw ValidationException::withMessages(['admin' => 'Unknown role.']);

        DB::transaction(function () use ($admin, $user, $adminRole): void {
            if (! $user->hasRole($adminRole->value)) {
                return;
            }

            if ($adminRole === AdminRole::Admin && User::query()->whereHas('roles', fn ($query) => $query->where('code', 'admin'))->lockForUpdate()->count() <= 1) {
                throw ValidationException::withMessages(['admin' => 'The last admin cannot be removed.']);
            }

            $user->roles()->detach($user->roles()->whereCode($adminRole->value)->pluck('roles.id')->all());
            $this->audit->handle($admin, 'role.revoked', $user, ['role' => $adminRole->value]);
        });

        return $user;
    }
}

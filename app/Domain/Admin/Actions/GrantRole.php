<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\AdminRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class GrantRole
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, User $user, string $role): User
    {
        $adminRole = AdminRole::tryFrom($role)
            ?? throw ValidationException::withMessages(['admin' => 'Unknown role.']);

        if ($user->hasRole($adminRole->value)) {
            return $user;
        }

        $model = Role::query()->firstOrCreate(['code' => $adminRole->value], ['name' => $adminRole->label()]);
        $user->roles()->syncWithoutDetaching([$model->id]);
        $this->audit->handle($admin, 'role.granted', $user, ['role' => $adminRole->value]);

        return $user;
    }
}

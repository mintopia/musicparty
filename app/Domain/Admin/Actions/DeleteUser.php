<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Actions\DeleteUser as IdentityDeleteUser;
use App\Domain\Identity\Models\User;
use App\Support\Realtime\Contracts\RealtimeConnections;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function __construct(
        private readonly RecordAdminAudit $audit,
        private readonly IdentityDeleteUser $deleteUser,
        private readonly RealtimeConnections $connections,
    ) {}

    public function handle(User $admin, User $user): void
    {
        if ($admin->is($user)) {
            throw ValidationException::withMessages(['admin' => 'You cannot delete yourself.']);
        }

        DB::transaction(function () use ($admin, $user): void {
            if ($user->hasRole('admin') && User::query()->whereHas('roles', fn ($query) => $query->where('code', 'admin'))->lockForUpdate()->count() <= 1) {
                throw ValidationException::withMessages(['admin' => 'The last admin cannot be deleted.']);
            }

            $userId = $user->id;
            $this->audit->handle($admin, 'user.deleted', $user, ['user_id' => $user->id, 'nickname' => $user->nickname]);
            ($this->deleteUser)($user);
            DB::afterCommit(fn () => $this->connections->terminateUser($userId));
        });
    }
}

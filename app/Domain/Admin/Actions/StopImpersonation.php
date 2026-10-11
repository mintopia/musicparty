<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;

class StopImpersonation
{
    public function __construct(private readonly RecordAdminAudit $audit) {}

    /**
     * Returns the restored admin, or null when the session was not impersonating or the admin is no longer valid.
     */
    public function handle(User $impersonated): ?User
    {
        $adminId = session()->pull(StartImpersonation::SESSION_KEY);

        if ($adminId === null) {
            return null;
        }

        $admin = User::query()->whereKey($adminId)->first();

        if ($admin === null || $admin->suspended || ! $admin->hasRole('admin')) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();

            return null;
        }

        $this->audit->handle($admin, 'impersonation.stopped', $impersonated);
        Auth::guard('web')->login($admin);
        session()->regenerate();

        return $admin;
    }
}

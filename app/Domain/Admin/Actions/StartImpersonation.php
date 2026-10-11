<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StartImpersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public function __construct(private readonly RecordAdminAudit $audit) {}

    public function handle(User $admin, User $user): User
    {
        if (session()->has(self::SESSION_KEY)) {
            throw ValidationException::withMessages(['admin' => 'You are already impersonating a user.']);
        }

        if ($admin->is($user)) {
            throw ValidationException::withMessages(['admin' => 'You cannot impersonate yourself.']);
        }

        if ($user->suspended) {
            throw ValidationException::withMessages(['admin' => 'You cannot impersonate a suspended user.']);
        }

        if ($user->hasRole('admin')) {
            throw ValidationException::withMessages(['admin' => 'You cannot impersonate another admin.']);
        }

        $this->audit->handle($admin, 'impersonation.started', $user);
        session()->put(self::SESSION_KEY, $admin->id);
        Auth::guard('web')->login($user);
        session()->regenerate();

        return $user;
    }
}

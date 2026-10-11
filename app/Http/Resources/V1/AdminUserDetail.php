<?php

namespace App\Http\Resources\V1;

use App\Domain\Admin\AdminRole;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;

/**
 * Inertia-only user detail payload; not an API resource, so it stays out of the OpenAPI export.
 */
class AdminUserDetail
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $user): array
    {
        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'email' => $user->getEmail(),
            'avatar_url' => $user->avatarUrl(),
            'suspended' => (bool) $user->suspended,
            'completed_signup' => ! $user->first_login,
            'agreed_terms' => $user->terms_agreed_at !== null,
            'last_login' => $user->last_login?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles->pluck('code')->values()->map(fn (string $code): array => [
                'code' => $code,
                'label' => AdminRole::tryFrom($code)?->label() ?? $code,
            ])->all(),
            'accounts' => $user->accounts->map(fn (LinkedAccount $account): array => [
                'id' => $account->id,
                'provider' => $account->provider?->name,
                'name' => $account->name,
                'email' => $account->email,
                'avatar_url' => $account->avatar_url,
                'external_id' => $account->external_id,
                'can_delete' => $account->canDelete(),
            ])->all(),
        ];
    }
}

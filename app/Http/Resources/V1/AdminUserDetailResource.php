<?php

namespace App\Http\Resources\V1;

use App\Domain\Admin\AdminRole;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'email' => $this->getEmail(),
            'avatar_url' => $this->avatarUrl(),
            'suspended' => (bool) $this->suspended,
            'completed_signup' => ! $this->first_login,
            'agreed_terms' => $this->terms_agreed_at !== null,
            'last_login' => $this->last_login?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'roles' => $this->roles->pluck('code')->unique()->values()->map(fn (string $code): array => [
                'code' => $code,
                'label' => AdminRole::tryFrom($code)?->label() ?? $code,
            ])->all(),
            'accounts' => $this->accounts->map(fn (LinkedAccount $account): array => [
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

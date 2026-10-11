<?php

namespace App\Http\Resources\V1;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'suspended' => (bool) $this->suspended,
            'roles' => $this->roles->pluck('code')->values()->all(),
            'last_login' => $this->last_login?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\V1;

use App\Models\PartyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PartyMember
 */
class PartyMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->holder()->nickname,
            'avatar' => $this->holder()->avatarUrl(),
            'role' => $this->role->value,
            'banned' => $this->banned,
            'is_you' => $request->user()?->id === $this->user_id,
        ];
    }
}

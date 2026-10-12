<?php

namespace App\Http\Resources;

use App\Domain\Membership\Models\PartyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PartyMember
 */
class PartyMemberRowResource extends JsonResource
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
            'isYou' => $request->user()?->id === $this->user_id,
            'joinedAt' => $this->created_at?->toJSON(),
        ];
    }
}

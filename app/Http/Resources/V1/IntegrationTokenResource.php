<?php

namespace App\Http\Resources\V1;

use App\Domain\Admin\Models\IntegrationToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IntegrationToken
 */
class IntegrationTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abilities' => $this->abilities,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'revoked' => $this->isRevoked(),
        ];
    }
}

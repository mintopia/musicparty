<?php

namespace App\Http\Resources\V1;

use App\Domain\Admin\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Integration
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
            'abilities' => $this->abilities(),
            'last_used_at' => $this->lastUsedAt()?->toIso8601String(),
            'revoked' => $this->isRevoked(),
        ];
    }
}

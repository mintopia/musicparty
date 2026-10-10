<?php

namespace App\Http\Resources\V1;

use App\Domain\Party\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Party $resource
 */
class PartyPlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'player_kind' => $this->resource->player_kind,
            'music_provider' => $this->resource->music_provider,
        ];
    }
}

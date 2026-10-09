<?php

namespace App\Http\Resources\V1;

use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Party
 */
class PartyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');

        return [
            'code' => $this->code,
            'name' => $this->name,
            'state' => $this->state->value,
            'music_provider' => $this->music_provider,
            'player_kind' => $this->player_kind,
            'role' => $user === null ? null : $this->memberFor($user)?->role->value,
        ];
    }
}

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
            'fallback_playlist_id' => $this->fallback_playlist_id,
            'explicit' => (bool) $this->explicit,
            'min_song_length' => $this->min_song_length,
            'max_song_length' => $this->max_song_length,
            'no_repeat_interval' => $this->no_repeat_interval,
            'hold_requests' => (bool) $this->hold_requests,
            'downvotes' => (bool) $this->downvotes,
            'downvotes_per_hour' => $this->downvotes_per_hour,
            'selection_mode' => $this->selection_mode->value,
            'role' => $user === null ? null : $this->memberFor($user)?->role->value,
        ];
    }
}

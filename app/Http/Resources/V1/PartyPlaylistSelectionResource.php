<?php

namespace App\Http\Resources\V1;

use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Party $resource
 */
class PartyPlaylistSelectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'fallback_playlist_id' => $this->resource->backup_playlist_id,
            'history_playlist_id' => $this->resource->history_playlist_id,
        ];
    }
}

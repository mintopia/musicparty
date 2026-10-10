<?php

namespace App\Http\Resources\V1;

use App\Domain\Music\Data\PlaylistData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PlaylistData $resource
 */
class PlaylistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'track_count' => $this->resource->trackCount,
        ];
    }
}

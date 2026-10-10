<?php

namespace App\Http\Resources\V1;

use App\Domain\Queue\Data\SearchHit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SearchHit
 */
class SearchHitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider_track_id' => $this->track->providerTrackId,
            'title' => $this->track->name,
            'artists' => array_map(fn ($artist): string => $artist->name, $this->track->artists),
            'album' => $this->track->album->name,
            'artwork_url' => $this->track->coverArtUrls[0] ?? null,
            'duration_ms' => $this->track->durationMs,
            'explicit' => $this->track->explicit,
            'provider_url' => $this->providerUrl,
            'queued' => $this->queued,
            'requested_by' => $this->requestedBy,
            'score' => $this->score,
        ];
    }
}

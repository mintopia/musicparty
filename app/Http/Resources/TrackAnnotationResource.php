<?php

namespace App\Http\Resources;

use App\Services\Music\Data\ArtistRef;
use App\Services\Requests\TrackAnnotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TrackAnnotation $resource
 */
class TrackAnnotationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $annotation = $this->resource;
        $track = $annotation->track;

        return [
            'provider_id' => $track->providerId,
            'id' => $track->id,
            'name' => $track->name,
            'duration_ms' => $track->durationMs,
            'explicit' => $track->explicit,
            'album' => [
                'id' => $track->album->id,
                'name' => $track->album->name,
                'image_url' => $track->album->imageUrl,
            ],
            'artists' => array_map(
                fn (ArtistRef $artist): array => ['id' => $artist->id, 'name' => $artist->name],
                $track->artists,
            ),
            'queue' => [
                'in_queue' => $annotation->upcoming,
                'upcoming_id' => $annotation->upcomingId,
                'score' => $annotation->score,
                'vote' => $annotation->vote,
                'requested_by' => $annotation->user,
            ],
            'eligibility' => [
                'allowed' => $annotation->allowed,
                'reason' => $annotation->reason,
            ],
        ];
    }
}

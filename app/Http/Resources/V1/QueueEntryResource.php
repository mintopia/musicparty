<?php

namespace App\Http\Resources\V1;

use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrackRequest
 */
class QueueEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $requester = $this->requester?->user;

        return [
            'id' => $this->id,
            'track' => [
                'title' => $this->title,
                'artists' => $this->artists,
                'album' => $this->album,
                'artwork_url' => $this->artwork_url,
                'duration_ms' => $this->duration_ms,
                'explicit' => $this->explicit,
            ],
            'status' => $this->status->value,
            'score' => (int) $this->score,
            'requested_by' => ['name' => $requester instanceof User ? $requester->nickname : null],
            'my_vote' => (int) $this->my_vote,
        ];
    }
}

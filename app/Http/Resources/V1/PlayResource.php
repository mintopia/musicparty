<?php

namespace App\Http\Resources\V1;

use App\Models\Play;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Play
 */
class PlayResource extends JsonResource
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
            'requested_by' => ['name' => $requester instanceof User ? $requester->nickname : null],
            'votes' => (int) $this->request?->votes_count,
            'score' => (int) $this->request?->score,
            'requested_at' => ($this->request instanceof TrackRequest ? $this->request->created_at : $this->played_at)?->toIso8601String(),
            'likes' => (int) $this->likes,
            'dislikes' => (int) $this->dislikes,
            'my_rating' => (int) $this->my_rating,
            'played_at' => $this->played_at->toIso8601String(),
        ];
    }
}

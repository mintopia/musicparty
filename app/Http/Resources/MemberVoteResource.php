<?php

namespace App\Http\Resources;

use App\Domain\Queue\Models\RequestVote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestVote
 */
class MemberVoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'votedAt' => $this->created_at?->toJSON(),
            'requestId' => $this->track_request_id,
            'title' => $this->request?->title,
            'artists' => $this->request?->artists,
        ];
    }
}

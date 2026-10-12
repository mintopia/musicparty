<?php

namespace App\Http\Resources\V1;

use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrackRequest
 */
class MemberRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artists' => $this->artists,
            'status' => $this->status->value,
            'requested_at' => $this->created_at?->toJSON(),
        ];
    }
}

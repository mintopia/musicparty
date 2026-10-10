<?php

namespace App\Http\Resources\V1;

use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\Presenters\QueueEntryPresenter;
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
        return [
            ...app(QueueEntryPresenter::class)($this->resource),
            'my_vote' => (int) $this->my_vote,
            ...($this->resource->relationLoaded('play') ? ['my_rating' => (int) $this->resource->play?->my_rating] : []),
        ];
    }
}

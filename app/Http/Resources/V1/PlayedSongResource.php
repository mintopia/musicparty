<?php

namespace App\Http\Resources\V1;

use App\Models\UpcomingSong;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UpcomingSong
 */
class PlayedSongResource extends JsonResource
{
    protected ?object $augmentedData = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->toApi();
        $data['id'] = $this->id;
        $data['created_at'] = $this->created_at->toIso8601String();
        $data['updated_at'] = $this->updated_at->toIso8601String();
        $data['rated'] = $this->augmentedData->rating->value ?? null;
        $data['user'] = $this->user->nickname ?? null;
        return $data;
    }

    public function augment(?object $data = null): void
    {
        $this->augmentedData = $data;
    }
}

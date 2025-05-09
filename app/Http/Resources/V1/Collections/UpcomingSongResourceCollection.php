<?php

namespace App\Http\Resources\V1\Collections;

use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\UpcomingSong;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UpcomingSongResourceCollection extends ResourceCollection
{
    protected array $augmentedData = [];

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->map(function (UpcomingSong $item) {
                $resource = new UpcomingSongResource($item);
                $resource->augment($this->augmentedData[$item->id] ?? null);
                return $resource;
            }),
        ];
    }

    public function augment(array $data = []): void
    {
        $this->augmentedData = $data;
    }
}

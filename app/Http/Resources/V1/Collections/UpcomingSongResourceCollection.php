<?php

namespace App\Http\Resources\V1\Collections;

use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\UpcomingSong;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class UpcomingSongResourceCollection extends ResourceCollection
{
    /** @var Collection<int, UpcomingSong> */
    public $collection;

    /** @var array<int, object> */
    protected array $augmentedData = [];

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function (UpcomingSong $item) {
                $resource = new UpcomingSongResource($item);
                $resource->augment($this->augmentedData[$item->id] ?? null);

                return $resource;
            }),
        ];
    }

    /**
     * @param  array<int, object>  $data
     */
    public function augment(array $data = []): void
    {
        $this->augmentedData = $data;
    }
}

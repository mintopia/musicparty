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

    /** @var array<int, array{held: bool, next: bool}> */
    protected array $queueStates = [];

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
                $resource->queueState($this->queueStates[$item->id] ?? null);

                return $resource;
            }),
        ];
    }

    /**
     * @param  array<int, array{held: bool, next: bool}>  $states
     */
    public function queueStates(array $states): void
    {
        $this->queueStates = $states;
    }

    /**
     * @param  array<int, object>  $data
     */
    public function augment(array $data = []): void
    {
        $this->augmentedData = $data;
    }
}

<?php

namespace App\Http\Resources\V1;

use App\Models\UpcomingSong;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UpcomingSong
 */
class UpcomingSongResource extends JsonResource
{
    protected ?object $augmentedData = null;

    protected ?int $position = null;

    /** @var array{held: bool, next?: bool, random_order?: bool}|null */
    protected ?array $queueState = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->toApi();
        $data['id'] = $this->id;
        $data['created_at'] = $this->created_at?->toIso8601String();
        $data['updated_at'] = $this->updated_at?->toIso8601String();
        $data['vote'] = $this->augmentedData->vote->value ?? null;
        $data['user'] = $this->user->nickname ?? null;
        if ($this->position !== null) {
            $data['position'] = $this->position;
        }
        if ($this->queueState !== null) {
            $data += $this->queueState;
        }

        return $data;
    }

    public function position(?int $position): void
    {
        $this->position = $position;
    }

    /**
     * @param  array{held: bool, next?: bool, random_order?: bool}|null  $state
     */
    public function queueState(?array $state): void
    {
        $this->queueState = $state;
    }

    public function augment(?object $data = null): void
    {
        $this->augmentedData = $data;
    }
}

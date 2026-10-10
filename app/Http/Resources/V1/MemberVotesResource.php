<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{votes: array<int, array{request_id: int, value: int}>, ratings: array<int, array{play_id: int, value: int}>} $resource
 */
class MemberVotesResource extends JsonResource
{
    /**
     * @return array{votes: array<int, array{request_id: int, value: int}>, ratings: array<int, array{play_id: int, value: int}>}
     */
    public function toArray(Request $request): array
    {
        return ['votes' => $this->resource['votes'], 'ratings' => $this->resource['ratings']];
    }
}

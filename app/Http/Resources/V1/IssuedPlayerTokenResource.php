<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * @property NewAccessToken $resource
 */
class IssuedPlayerTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...new PlayerTokenResource($this->resource->accessToken)->toArray($request),
            'token' => $this->resource->plainTextToken,
        ];
    }
}

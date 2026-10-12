<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{linked: bool, name: string|null, needs_relink: bool} $resource
 */
class SpotifyLinkStatusResource extends JsonResource
{
    public static $wrap = 'data';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'linked' => $this->resource['linked'],
            'name' => $this->resource['name'],
            'needs_relink' => $this->resource['needs_relink'],
        ];
    }
}

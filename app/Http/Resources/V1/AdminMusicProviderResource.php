<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{id: string, label: string, enabled: bool} $resource
 */
class AdminMusicProviderResource extends JsonResource
{
    /**
     * @return array{id: string, label: string, enabled: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'label' => $this->resource['label'],
            'enabled' => $this->resource['enabled'],
        ];
    }
}

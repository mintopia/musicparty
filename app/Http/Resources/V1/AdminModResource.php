<?php

namespace App\Http\Resources\V1;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\SettingDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Mod
 */
class AdminModResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'description' => $this->description(),
            'definitions' => array_map(fn (SettingDefinition $definition): array => [
                'key' => $definition->key,
                'label' => $definition->label,
                'kind' => $definition->kind->value,
                'min' => $definition->min,
                'max' => $definition->max,
                'options' => $definition->options,
                'required' => $definition->required,
            ], $this->settings()),
        ];
    }
}

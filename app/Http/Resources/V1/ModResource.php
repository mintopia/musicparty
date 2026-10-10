<?php

namespace App\Http\Resources\V1;

use App\Domain\Mod\Data\ModStatus;
use App\Domain\Mod\Data\SettingDefinition;
use App\Domain\Mod\SettingKind;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModStatus
 */
class ModResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->mod->id(),
            'name' => $this->mod->name(),
            'description' => $this->mod->description(),
            'enabled' => $this->enabled,
            'definitions' => array_map(fn (SettingDefinition $definition): array => [
                'key' => $definition->key,
                'label' => $definition->label,
                'kind' => $definition->kind->value,
                'default' => $definition->kind === SettingKind::Secret ? null : $definition->default,
                'min' => $definition->min,
                'max' => $definition->max,
                'options' => $definition->options,
                'required' => $definition->required,
            ], $this->definitions),
            'settings' => (object) $this->settings,
        ];
    }
}

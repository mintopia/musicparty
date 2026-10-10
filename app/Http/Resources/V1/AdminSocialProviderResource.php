<?php

namespace App\Http\Resources\V1;

use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Admin\ProviderCatalogue;
use App\Domain\Identity\Models\SocialProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SocialProvider
 */
class AdminSocialProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'enabled' => (bool) $this->enabled,
            'configured' => $this->isConfigured(),
            'fields' => $this->settings->map(fn (ProviderSetting $setting): array => [
                'code' => $setting->code,
                'name' => $setting->name,
                'secret' => (bool) $setting->encrypted,
                'value' => $setting->encrypted
                    ? (filled($setting->value) ? ProviderCatalogue::MASK : null)
                    : $setting->value,
            ])->values()->all(),
        ];
    }
}

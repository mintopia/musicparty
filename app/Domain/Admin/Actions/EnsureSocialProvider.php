<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Admin\ProviderCatalogue;
use App\Domain\Identity\Models\SocialProvider;
use App\Enums\SettingType;
use Illuminate\Support\Facades\DB;

class EnsureSocialProvider
{
    public function handle(string $code): SocialProvider
    {
        $definition = ProviderCatalogue::all()[$code] ?? abort(404);

        return DB::transaction(function () use ($code, $definition): SocialProvider {
            $provider = SocialProvider::query()->where('code', $code)->first() ?? new SocialProvider;
            if (! $provider->exists) {
                $provider->forceFill([
                    'code' => $code,
                    'name' => $definition['name'],
                    'provider_class' => $definition['class'],
                    'supports_auth' => true,
                    'enabled' => false,
                    'auth_enabled' => false,
                    'can_be_renamed' => false,
                ])->save();
            }

            foreach ($definition['fields'] as $fieldCode => $field) {
                if ($provider->settings()->whereCode($fieldCode)->exists()) {
                    continue;
                }
                $setting = new ProviderSetting;
                $setting->provider()->associate($provider);
                $setting->forceFill([
                    'code' => $fieldCode,
                    'name' => $field['name'],
                    'type' => SettingType::stString,
                    'encrypted' => $field['secret'],
                    'validation' => 'required|string',
                    'value' => null,
                ])->save();
            }

            return $provider->load('settings');
        });
    }
}

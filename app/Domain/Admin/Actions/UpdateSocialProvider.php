<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Admin\ProviderCatalogue;
use App\Domain\Identity\Actions\SetSocialProviderAuthEnabled;
use App\Domain\Identity\Actions\SetSocialProviderEnabled;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateSocialProvider
{
    public function __construct(
        private readonly EnsureSocialProvider $ensure,
        private readonly RecordAdminAudit $audit,
        private readonly SetSocialProviderEnabled $setEnabled,
        private readonly SetSocialProviderAuthEnabled $setAuthEnabled,
    ) {}

    /**
     * @param  array<string, string|null>  $settings
     */
    public function handle(User $admin, string $code, ?bool $enabled, array $settings, ?bool $authEnabled = null): SocialProvider
    {
        $provider = $this->ensure->handle($code);

        return DB::transaction(function () use ($admin, $code, $enabled, $settings, $authEnabled, $provider): SocialProvider {
            foreach ($provider->settings as $setting) {
                $value = $settings[$setting->code] ?? null;
                if (! is_string($value) || trim($value) === '' || $value === ProviderCatalogue::MASK || $value === $setting->value) {
                    continue;
                }
                $this->store($setting, $value);
                $this->audit->handle($admin, 'provider.credential_changed', null, ['provider' => $code, 'field' => $setting->code]);
            }

            $provider->load('settings');

            $authBefore = (bool) $provider->auth_enabled;

            if ($enabled !== null && $enabled !== (bool) $provider->enabled) {
                ($this->setEnabled)($provider, $enabled, $authEnabled);
                $this->audit->handle($admin, $enabled ? 'provider.enabled' : 'provider.disabled', null, ['provider' => $code]);
            } elseif ($authEnabled !== null && $authEnabled !== $authBefore) {
                ($this->setAuthEnabled)($provider, $authEnabled);
            }

            if ((bool) $provider->auth_enabled !== $authBefore) {
                $this->audit->handle($admin, $provider->auth_enabled ? 'provider.auth_enabled' : 'provider.auth_disabled', null, ['provider' => $code]);
            }

            $provider->wasRecentlyCreated = false;

            return $provider;
        });
    }

    private function store(ProviderSetting $setting, string $value): void
    {
        $setting->value = $value;
        $setting->save();
    }
}

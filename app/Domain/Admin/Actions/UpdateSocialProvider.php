<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\ProviderSetting;
use App\Domain\Admin\ProviderCatalogue;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSocialProvider
{
    public function __construct(
        private readonly EnsureSocialProvider $ensure,
        private readonly RecordAdminAudit $audit,
    ) {}

    /**
     * @param  array<string, string|null>  $settings
     */
    public function handle(User $admin, string $code, ?bool $enabled, array $settings): SocialProvider
    {
        $provider = $this->ensure->handle($code);

        return DB::transaction(function () use ($admin, $code, $enabled, $settings, $provider): SocialProvider {
            foreach ($provider->settings as $setting) {
                $value = $settings[$setting->code] ?? null;
                if (! is_string($value) || trim($value) === '' || $value === ProviderCatalogue::MASK || $value === $setting->value) {
                    continue;
                }
                $this->store($setting, $value);
                $this->audit->handle($admin, 'provider.credential_changed', null, ['provider' => $code, 'field' => $setting->code]);
            }

            $provider->load('settings');

            if ($enabled !== null && $enabled !== (bool) $provider->enabled) {
                if ($enabled && ! $provider->isConfigured()) {
                    throw ValidationException::withMessages(['enabled' => 'Set the credentials before enabling this provider.']);
                }
                $provider->forceFill(['enabled' => $enabled, 'auth_enabled' => $enabled])->save();
                $this->audit->handle($admin, $enabled ? 'provider.enabled' : 'provider.disabled', null, ['provider' => $code]);
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

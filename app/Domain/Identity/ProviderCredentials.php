<?php

namespace App\Domain\Identity;

use App\Domain\Identity\Models\SocialProvider;

/**
 * Reads provider credentials saved in the Admin UI. Bound scoped, so each request or job reads the database at most once per provider.
 */
class ProviderCredentials
{
    /**
     * @var array<string, SocialProvider|null>
     */
    private array $providers = [];

    public function get(string $providerCode, string $settingCode): ?string
    {
        $value = $this->provider($providerCode)?->settings->firstWhere('code', $settingCode)?->value;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isConfigured(string $providerCode): bool
    {
        return $this->provider($providerCode)?->isConfigured() ?? false;
    }

    private function provider(string $providerCode): ?SocialProvider
    {
        if (! array_key_exists($providerCode, $this->providers)) {
            $this->providers[$providerCode] = SocialProvider::query()->with('settings')->where('code', $providerCode)->first();
        }

        return $this->providers[$providerCode];
    }
}

<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\SocialProvider;
use Illuminate\Validation\ValidationException;

readonly class SetSocialProviderAuthEnabled
{
    public function __invoke(SocialProvider $provider, bool $authEnabled): SocialProvider
    {
        if ($authEnabled && ! $provider->supports_auth) {
            throw ValidationException::withMessages(['auth_enabled' => 'This provider does not support authentication.']);
        }

        if ($authEnabled && ! $provider->enabled) {
            throw ValidationException::withMessages(['auth_enabled' => 'Enable the provider before enabling authentication.']);
        }

        if ($authEnabled && ! $provider->load('settings')->isConfigured()) {
            throw ValidationException::withMessages(['auth_enabled' => 'Set the credentials before enabling authentication.']);
        }

        $provider->forceFill(['auth_enabled' => $authEnabled])->save();

        return $provider;
    }
}

<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\SocialProvider;
use Illuminate\Validation\ValidationException;

readonly class SetSocialProviderEnabled
{
    public function __invoke(SocialProvider $provider, bool $enabled, ?bool $authEnabled = null): SocialProvider
    {
        if ($enabled && ! $provider->load('settings')->isConfigured()) {
            throw ValidationException::withMessages(['enabled' => 'Set the credentials before enabling this provider.']);
        }

        $provider->forceFill(['enabled' => $enabled, 'auth_enabled' => $enabled && ($authEnabled ?? true)])->save();

        return $provider;
    }
}

<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\SocialProvider;

readonly class SetSocialProviderEnabled
{
    public function __invoke(SocialProvider $provider, bool $enabled): SocialProvider
    {
        $provider->forceFill(['enabled' => $enabled, 'auth_enabled' => $enabled])->save();

        return $provider;
    }
}

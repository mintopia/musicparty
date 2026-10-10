<?php

namespace App\Domain\Identity\SocialProviders;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider as SocialiteGoogleProvider;

class GoogleProvider extends AbstractSocialProvider
{
    protected string $name = 'Google';

    protected string $code = 'google';

    protected string $socialiteProviderCode = 'google';

    protected bool $supportsAuth = true;

    protected function getSocialiteProvider(): Provider
    {
        return Socialite::buildProvider(SocialiteGoogleProvider::class, [
            'client_id' => $this->provider?->getSetting('client_id'),
            'client_secret' => $this->provider?->getSetting('client_secret'),
            'redirect' => $this->redirectUrl,
        ]);
    }
}

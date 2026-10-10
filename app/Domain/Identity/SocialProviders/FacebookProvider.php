<?php

namespace App\Domain\Identity\SocialProviders;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider as SocialiteFacebookProvider;

class FacebookProvider extends AbstractSocialProvider
{
    protected string $name = 'Facebook';

    protected string $code = 'facebook';

    protected string $socialiteProviderCode = 'facebook';

    protected bool $supportsAuth = true;

    protected function getSocialiteProvider(): Provider
    {
        return Socialite::buildProvider(SocialiteFacebookProvider::class, [
            'client_id' => $this->provider?->getSetting('client_id'),
            'client_secret' => $this->provider?->getSetting('client_secret'),
            'redirect' => $this->redirectUrl,
        ]);
    }
}

<?php

namespace App\Services\SocialProviders;

use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Discord\Provider;

class DiscordProvider extends AbstractSocialProvider
{
    protected string $name = 'Discord';
    protected string $code = 'discord';
    protected string $socialiteProviderCode = 'discord';
    protected bool $supportsAuth = true;

    protected function getSocialiteProvider()
    {
        return Socialite::buildProvider(Provider::class, [
            'client_id' => $this->provider->getSetting('client_id'),
            'client_secret' => $this->provider->getSetting('client_secret'),
            'redirect' => $this->redirectUrl,
        ]);
    }
}

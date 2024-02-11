<?php

namespace App\Services\SocialProviders;

use App\Models\LinkedAccount;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Twitch\Provider;

class TwitchProvider extends AbstractSocialProvider
{
    protected string $name = 'Twitch';
    protected string $code = 'twitch';
    protected string $socialiteProviderCode = 'twitch';
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

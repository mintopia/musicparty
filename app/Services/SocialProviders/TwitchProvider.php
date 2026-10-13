<?php

namespace App\Services\SocialProviders;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Twitch\Provider;

class TwitchProvider extends AbstractSocialProvider
{
    protected string $name = 'Twitch';

    protected string $code = 'twitch';

    protected string $socialiteProviderCode = 'twitch';

    protected bool $supportsAuth = true;

    protected function getSocialiteProvider(): AbstractProvider
    {
        return Socialite::buildProvider(Provider::class, [
            'client_id' => $this->getSetting('client_id'),
            'client_secret' => $this->getSetting('client_secret'),
            'redirect' => $this->redirectUrl,
        ]);
    }

    protected function emailIsVerified(SocialiteUser $remoteUser): bool
    {
        return true;
    }
}

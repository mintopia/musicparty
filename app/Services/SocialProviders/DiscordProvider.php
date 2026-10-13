<?php

namespace App\Services\SocialProviders;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Discord\Provider;

class DiscordProvider extends AbstractSocialProvider
{
    protected string $name = 'Discord';

    protected string $code = 'discord';

    protected string $socialiteProviderCode = 'discord';

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
        return ($remoteUser->user['verified'] ?? false) === true;
    }
}

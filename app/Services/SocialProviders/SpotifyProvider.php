<?php

namespace App\Services\SocialProviders;

use App\Models\LinkedAccount;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Spotify\Provider;

class SpotifyProvider extends AbstractSocialProvider
{
    protected string $name = 'Spotify';
    protected string $code = 'spotify';
    protected string $socialiteProviderCode = 'spotify';
    protected bool $supportsAuth = true;

    protected function getSocialiteProvider()
    {
        return Socialite::buildProvider(Provider::class, [
            'client_id' => $this->provider->getSetting('client_id'),
            'client_secret' => $this->provider->getSetting('client_secret'),
            'redirect' => $this->redirectUrl,
        ]);
    }

    protected function updateAccount(LinkedAccount $account, $remoteUser): void
    {
        $account->avatar_url = $remoteUser->getAvatar();
        $account->refresh_token = $remoteUser->refreshToken;
        $account->access_token = $remoteUser->token;
        $account->name = $remoteUser->getName();
        $account->email = $remoteUser->getEmail();
    }
}

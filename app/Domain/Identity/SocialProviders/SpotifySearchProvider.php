<?php

namespace App\Domain\Identity\SocialProviders;

use App\Domain\Identity\Models\LinkedAccount;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as OAuthTwoUser;
use SocialiteProviders\Spotify\Provider;

class SpotifySearchProvider extends AbstractSocialProvider
{
    protected string $name = 'Spotify Search';

    protected string $code = 'spotifysearch';

    protected string $socialiteProviderCode = 'spotify';

    protected bool $supportsAuth = false;

    protected function getSocialiteProvider(): \Laravel\Socialite\Contracts\Provider
    {
        return Socialite::buildProvider(Provider::class, [
            'client_id' => $this->provider?->getSetting('client_id'),
            'client_secret' => $this->provider?->getSetting('client_secret'),
            'redirect' => $this->redirectUrl,
        ]);
    }

    protected function updateAccount(LinkedAccount $account, SocialiteUser $remoteUser): void
    {
        if ($remoteUser instanceof OAuthTwoUser) {
            $account->refresh_token = $remoteUser->refreshToken;
            $account->access_token = $remoteUser->token;
        }

        $account->name = $remoteUser->getName();
    }
}

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
        ])->scopes([
            'user-read-playback-state',
            'user-modify-playback-state',
            'user-read-currently-playing',
            'playlist-read-private',
            'playlist-read-collaborative',
            'playlist-modify-private',
            'playlist-modify-public',
            'user-read-playback-position',
            'user-top-read',
            'user-read-recently-played',
            'user-library-modify',
            'user-library-read',
            'user-read-email',
            'user-read-private',
            'streaming',
            'app-remote-control',
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

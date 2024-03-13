<?php

namespace App\Services\SocialProviders;

use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Spotify\Provider;

class SpotifySearchProvider extends SpotifyProvider
{
    protected string $name = 'Spotify Search';
    protected string $code = 'spotifysearch';
    protected bool $supportsAuth = false;

    protected function getSocialiteProvider()
    {
        return Socialite::buildProvider(Provider::class, [
            'client_id' => $this->provider->getSetting('client_id'),
            'client_secret' => $this->provider->getSetting('client_secret'),
            'redirect' => $this->redirectUrl,
        ]);
    }
}

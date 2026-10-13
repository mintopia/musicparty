<?php

namespace App\Services\SocialProviders;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use SocialiteProviders\Spotify\Provider;

class SpotifySearchProvider extends SpotifyProvider
{
    protected string $name = 'Spotify Search';

    protected string $code = 'spotifysearch';

    protected bool $supportsAuth = false;

    protected function getSocialiteProvider(): AbstractProvider
    {
        return Socialite::buildProvider(Provider::class, [
            'client_id' => $this->getSetting('client_id'),
            'client_secret' => $this->getSetting('client_secret'),
            'redirect' => $this->redirectUrl,
        ]);
    }
}

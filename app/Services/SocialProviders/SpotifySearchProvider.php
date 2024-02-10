<?php

namespace App\Services\SocialProviders;

use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Spotify\Provider;

class SpotifySearchProvider extends SpotifyProvider
{
    protected string $name = 'Spotify Search';
    protected string $code = 'spotifysearch';
    protected bool $supportsAuth = false;
}

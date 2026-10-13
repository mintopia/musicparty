<?php

namespace App\Services\Spotify;

use App\Exceptions\ProviderRelinkRequiredException;
use RuntimeException;

class SpotifyRelinkRequiredException extends RuntimeException implements ProviderRelinkRequiredException
{
    public function __construct()
    {
        parent::__construct('Spotify account needs re-linking');
    }
}

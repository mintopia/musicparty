<?php

namespace App\Services\Spotify;

use RuntimeException;

class SpotifyRelinkRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Spotify account needs re-linking');
    }
}

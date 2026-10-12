<?php

namespace Tests\Fixtures\Architecture;

class SpotifyEnvCredentialReader
{
    public function clientId(): ?string
    {
        return config('services.spotify.client_id');
    }
}

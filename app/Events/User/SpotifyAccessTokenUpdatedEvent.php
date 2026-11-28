<?php

namespace App\Events\User;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class SpotifyAccessTokenUpdatedEvent
{
    use Dispatchable;

    public function __construct(public User $user, public string $accessToken) {}
}

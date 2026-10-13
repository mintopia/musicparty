<?php

namespace App\Listeners;

use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Events\WebPlayerTokenRefreshed;
use App\Models\Party;

class BroadcastWebPlayerToken
{
    public function handle(SpotifyAccessTokenUpdatedEvent $event): void
    {
        Party::query()->where('user_id', $event->user->id)->each(
            fn (Party $party) => WebPlayerTokenRefreshed::dispatch($party, $event->accessToken),
        );
    }
}

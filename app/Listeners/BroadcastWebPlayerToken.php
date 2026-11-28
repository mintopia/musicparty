<?php

namespace App\Listeners;

use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Events\WebPlayerTokenRefreshed;
use App\Models\Party;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastWebPlayerToken
{
    public function handle(SpotifyAccessTokenUpdatedEvent $event): void
    {
        Party::query()->where('user_id', $event->user->id)->each(
            function (Party $party) use ($event): void {
                try {
                    WebPlayerTokenRefreshed::dispatch($party, $event->accessToken);
                } catch (Throwable $e) {
                    Log::warning("Web player token broadcast failed for party {$party->id}: {$e->getMessage()}");
                }
            },
        );
    }
}

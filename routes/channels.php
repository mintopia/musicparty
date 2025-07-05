<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('party.{party}', function ($user, Party $party) {
    return true;
});

Broadcast::channel('party.{party}.owner', function($user, Party $party) {
    return $party->canBeManagedBy($user);
});

Broadcast::channel('spotifytoken.{userId}', function($user, int $userId) {
    return $user->id === $userId;
});

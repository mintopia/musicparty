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

Broadcast::channel('party.{party}', fn ($user, Party $party) => true);

Broadcast::channel('party.{party}.owner', fn ($user, Party $party) => $party->canBeManagedBy($user));

Broadcast::channel('spotifytoken.{userId}', fn ($user, int $userId) => $user->id === $userId);

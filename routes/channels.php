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
use Illuminate\Support\Facades\Gate;

Broadcast::channel('party.{party}', fn ($user, Party $party) => true);

Broadcast::channel('party.{party}.owner', fn ($user, Party $party) => $party->canBeManagedBy($user));

Broadcast::channel('spotifytoken.{userId}', fn ($user, int $userId) => (int) $user->id === (int) $userId);

Broadcast::channel('party.{party}.managers', fn ($user, Party $party) => $party->canBeManagedBy($user));

Broadcast::channel('user.{userId}', fn ($user, int $userId) => (int) $user->id === (int) $userId);

Broadcast::channel('webplayer.{party}', fn (User $user, Party $party) => Gate::forUser($user)->allows('player', $party));

Broadcast::channel('encore.{code}', fn () => false);

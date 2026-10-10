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

use App\Domain\Party\PartyRole;
use App\Http\Middleware\EnsurePlayerToken;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('party.{party}', fn ($user, Party $party) => true);

Broadcast::channel('party.{party}.owner', fn ($user, Party $party) => $party->canBeManagedBy($user));

Broadcast::channel('spotifytoken.{userId}', fn ($user, int $userId) => $user->id === $userId);

Broadcast::channel('party.{code}.members', function (User $user, string $code): array|false {
    $party = Party::findByCode($code);
    $member = $party?->memberFor($user);

    if ($member === null || $member->banned) {
        return false;
    }

    return ['nickname' => $user->nickname, 'avatar' => $user->avatarUrl()];
});

Broadcast::channel('party.{code}.member.{memberId}', function (User $user, string $code, string $memberId): bool {
    $member = Party::findByCode($code)?->memberFor($user);

    return $member !== null && ! $member->banned && $member->id === (int) $memberId;
});

Broadcast::channel('party.{code}.moderators', function (User $user, string $code): bool {
    $member = Party::findByCode($code)?->memberFor($user);

    return $member !== null && ! $member->banned
        && in_array($member->role, [PartyRole::Host, PartyRole::Moderator], true);
});

Broadcast::channel('player.{code}', function (mixed $principal, string $code): bool {
    $party = Party::findByCode($code);

    return $principal instanceof Party && $party !== null && $principal->is($party)
        && $principal->tokenCan(EnsurePlayerToken::ABILITY);
}, ['guards' => ['sanctum']]);

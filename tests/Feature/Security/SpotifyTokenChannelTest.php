<?php

use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;

function tokenChannelAuthorises(User $user, int $channelUserId): bool
{
    $callback = Broadcast::driver()->getChannels()->get('spotifytoken.{userId}');

    return (bool) $callback($user, $channelUserId);
}

it('broadcasts the token update on a private channel for the owning user', function () {
    $user = User::factory()->create();

    $channels = new SpotifyAccessTokenUpdatedEvent($user, 'secret')->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-spotifytoken.{$user->id}");
});

it('authorises only the matching user on the token channel', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    expect(tokenChannelAuthorises($owner, $owner->id))->toBeTrue()
        ->and(tokenChannelAuthorises($other, $owner->id))->toBeFalse();
});

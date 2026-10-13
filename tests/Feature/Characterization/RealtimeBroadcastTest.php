<?php

use App\Events\Cron\DayTickEvent;
use App\Events\Cron\HourTickEvent;
use App\Events\Cron\MinuteTickEvent;
use App\Events\Mods\PartyModSettingDeletedEvent;
use App\Events\Mods\PartyModSettingUpdatedEvent;
use App\Events\Party\PlayYouTubeVideoEvent;
use App\Events\Party\UpdatedEvent as PartyUpdatedEvent;
use App\Events\UpcomingSong\RemovedEvent;
use App\Events\UpcomingSong\UpdatedEvent as UpcomingSongUpdatedEvent;
use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\PlaybackSync;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeSpotify;

function realtimeParty(string $code = 'RTPT'): Party
{
    $party = Party::factory()->create(['code' => $code]);

    return $party->refresh();
}

function channelCallback(string $pattern): Closure
{
    return Broadcast::driver()->getChannels()->get($pattern);
}

it('broadcasts the party state on the public party channel', function () {
    $party = realtimeParty();
    $event = new PartyUpdatedEvent($party);

    $channels = $event->broadcastOn();

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($channels)->toHaveCount(1)
        ->and($channels[0])->not->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe('party.RTPT')
        ->and(method_exists($event, 'broadcastAs'))->toBeFalse();
});

it('shapes the party update payload as the party state', function () {
    $payload = new PartyUpdatedEvent(realtimeParty())->broadcastWith();

    expect($payload)->toHaveKeys([
        'code', 'name', 'backup_playlist_id', 'status', 'now', 'current', 'next',
        'show_qrcode', 'created_at', 'updated_at',
    ])
        ->and($payload['code'])->toBe('RTPT')
        ->and($payload['status'])->toBeObject()
        ->and((array) $payload['status'])->toHaveKeys([
            'device', 'repeat', 'shuffle', 'active', 'isPlaying', 'progress', 'length', 'updatedAt',
        ]);
});

it('broadcasts the YouTube video on the public party channel with a video payload', function () {
    $event = new PlayYouTubeVideoEvent(realtimeParty(), 'abc123');

    $channels = $event->broadcastOn();

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($channels)->toHaveCount(1)
        ->and($channels[0])->not->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('party.RTPT')
        ->and($event->broadcastWith())->toBe(['video' => 'abc123']);
});

it('broadcasts an upcoming song update on the private party channel with the song shape', function () {
    $party = realtimeParty();
    $song = UpcomingSong::factory()->for($party)->create();
    $event = new UpcomingSongUpdatedEvent($song);

    $channels = $event->broadcastOn();
    $payload = $event->broadcastWith();

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-party.RTPT')
        ->and($payload)->toHaveKeys([
            'score', 'queued_at', 'user', 'fallback_name', 'css_classes', 'id', 'created_at', 'updated_at', 'vote',
        ])
        ->and($payload['id'])->toBe($song->id)
        ->and($payload['vote'])->toBeNull()
        ->and($payload['queued_at'])->toBeNull()
        ->and($payload['fallback_name'])->toBe('Fallback Track')
        ->and($payload['created_at'])->toBe($song->created_at->toIso8601String());
});

it('broadcasts an upcoming song removal on the private party channel with only the id', function () {
    $event = new RemovedEvent(realtimeParty(), 42);

    $channel = $event->broadcastOn();

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-party.RTPT')
        ->and($event->broadcastWith())->toBe(['id' => 42]);
});

it('broadcasts the Spotify token on the private per-user channel', function () {
    $user = User::factory()->create();
    $event = new SpotifyAccessTokenUpdatedEvent($user, 'tok');

    $channels = $event->broadcastOn();

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-spotifytoken.{$user->id}")
        ->and($event->broadcastWith())->toBe(['accessToken' => 'tok']);
});

it('does not broadcast the cron tick and mod setting events, which are placeholders', function (string $class) {
    expect(is_subclass_of($class, ShouldBroadcast::class))->toBeFalse();
})->with([
    MinuteTickEvent::class, HourTickEvent::class, DayTickEvent::class,
    PartyModSettingUpdatedEvent::class, PartyModSettingDeletedEvent::class,
]);

it('declares the placeholder channel-name channel on the cron ticks and mod setting deletion', function (string $event) {
    $channels = new ReflectionClass($event)->newInstanceWithoutConstructor()->broadcastOn();

    expect($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-channel-name');
})->with([MinuteTickEvent::class, HourTickEvent::class, DayTickEvent::class, PartyModSettingDeletedEvent::class]);

it('does not broadcast Party UpdatedEvent for bookkeeping bumps to last_updated_at', function () {
    $party = realtimeParty();
    Event::fake([PartyUpdatedEvent::class]);

    $party->last_updated_at = now()->addMinute();
    $party->save();

    Event::assertNotDispatched(PartyUpdatedEvent::class);
});

it('broadcasts Party UpdatedEvent once when a rendered field changes', function () {
    $party = realtimeParty();
    Event::fake([PartyUpdatedEvent::class]);

    $party->name = 'Renamed';
    $party->save();

    Event::assertDispatchedTimes(PartyUpdatedEvent::class, 1);
});

function broadcastSyncParty(bool $playing): array
{
    FakeSpotify::install();
    $party = Party::factory()->create(['active' => true, 'backup_playlist_id' => 'BACKUP']);
    $fake = app(FakeMusicProvider::class);
    $fake->withTrack(new Track('fake', 'SYNCED', 'Name SYNCED', 200000, false, true, new AlbumRef('album-SYNCED', 'Test Album', null), [new ArtistRef('artist1', 'Test Artist')]));
    $fake->withPlayback(broadcastPlayback($playing));

    return [$party, $fake];
}

function broadcastPlayback(bool $playing): PlaybackState
{
    return new PlaybackState($playing, 'SYNCED', null, 200000, 1000, CarbonImmutable::now(), false, 'off', 'device1', 'Test Speaker', PlaybackSource::Polling);
}

it('broadcasts once per sync when the track, playback status and device change', function () {
    [$party] = broadcastSyncParty(true);
    Event::fake([PartyUpdatedEvent::class]);

    app(PlaybackSync::class)->sync($party);

    Event::assertDispatchedTimes(PartyUpdatedEvent::class, 1);
});

it('broadcasts when only the playback status changes during a sync', function () {
    [$party, $fake] = broadcastSyncParty(true);
    app(PlaybackSync::class)->sync($party);
    $fake->withPlayback(broadcastPlayback(false));
    Event::fake([PartyUpdatedEvent::class]);

    app(PlaybackSync::class)->sync($party);

    Event::assertDispatchedTimes(PartyUpdatedEvent::class, 1);
});

it('does not broadcast when a sync changes nothing that clients render', function () {
    [$party] = broadcastSyncParty(false);
    app(PlaybackSync::class)->sync($party);
    Event::fake([PartyUpdatedEvent::class]);

    app(PlaybackSync::class)->sync($party);

    Event::assertNotDispatched(PartyUpdatedEvent::class);
});

it('builds the broadcast payload from state at dispatch time', function () {
    $party = realtimeParty();
    $event = new PartyUpdatedEvent($party);

    $party->name = 'Changed After Dispatch';
    $party->save();

    expect($event->broadcastWith()['name'])->not->toBe('Changed After Dispatch');
});

it('allows any authenticated user, member or not, onto the party channel (pins today: returns true)', function () {
    $party = realtimeParty();

    $callback = channelCallback('party.{party}');

    expect($callback(User::factory()->create(), $party))->toBeTrue()
        ->and($callback(new User, $party))->toBeTrue();
});

it('authorises the party owner channel for the host, an owner-role member and not for others', function () {
    $party = realtimeParty();
    $callback = channelCallback('party.{party}.owner');

    $ownerMember = User::factory()->create();
    $role = PartyMemberRole::query()->forceCreate(['code' => 'owner', 'name' => 'Owner']);
    $row = new PartyMember;
    $row->party_id = $party->id;
    $row->user_id = $ownerMember->id;
    $row->role_id = $role->id;
    $row->save();

    $plainRole = PartyMemberRole::query()->forceCreate(['code' => 'user', 'name' => 'User']);
    $plainMember = User::factory()->create();
    $plain = new PartyMember;
    $plain->party_id = $party->id;
    $plain->user_id = $plainMember->id;
    $plain->role_id = $plainRole->id;
    $plain->save();

    expect($callback($party->user, $party))->toBeTrue()
        ->and($callback($ownerMember, $party))->toBeTrue()
        ->and($callback($plainMember, $party))->toBeFalse()
        ->and($callback(User::factory()->create(), $party))->toBeFalse();
});

it('authorises the Spotify token channel only for the matching user id', function () {
    $callback = channelCallback('spotifytoken.{userId}');
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect($callback($user, $user->id))->toBeTrue()
        ->and($callback($user, (string) $user->id))->toBeTrue()
        ->and($callback($other, $user->id))->toBeFalse();
});

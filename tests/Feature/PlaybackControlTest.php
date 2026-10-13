<?php

use App\Events\Party\PlayerCommand;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Fake\FakeMusicProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

function controlParty(array $attributes = []): Party
{
    $party = Party::factory()->create($attributes);
    Sanctum::actingAs($party->user);

    return $party;
}

function playingState(string $trackId): PlaybackState
{
    return new PlaybackState(true, $trackId, null, 180000, 1000, CarbonImmutable::now(), false, 'off', 'fake-device', 'Fake Speaker', PlaybackSource::Polling);
}

it('plays the current track on the given device through the provider', function () {
    $fake = app(FakeMusicProvider::class)
        ->withTrack(FakeFallbackPlaylist::track('cur'))
        ->withPlayback(playingState('cur'));
    $party = controlParty();

    $this->postJson(route('api.v1.parties.control', $party), ['action' => 'play', 'deviceId' => 'dev9'])->assertOk();

    $calls = $fake->callsTo('play');
    expect($calls)->toHaveCount(1)
        ->and($calls[0][0])->toBe('dev9')
        ->and($calls[0][1]->id)->toBe('cur');
});

it('transfers playback to the web player device before playing', function () {
    $fake = app(FakeMusicProvider::class)
        ->withoutActiveDevice()
        ->withTrack(FakeFallbackPlaylist::track('cur'))
        ->withPlayback(playingState('cur'));
    $party = controlParty();

    $this->postJson(route('api.v1.parties.control', $party), ['action' => 'play', 'deviceId' => 'web-sdk'])->assertOk();

    $methods = array_column($fake->calls(), 'method');
    expect($fake->callsTo('transferPlayback'))->toBe([['web-sdk']])
        ->and(array_search('transferPlayback', $methods, true))->toBeLessThan(array_search('play', $methods, true))
        ->and($fake->callsTo('play')[0][0])->toBe('web-sdk');
});

it('returns a client error rather than a server error when there is no active device', function () {
    app(FakeMusicProvider::class)->withoutActiveDevice();
    $party = controlParty();

    $this->postJson(route('api.v1.parties.control', $party), ['action' => 'play'])->assertStatus(409);
});

it('routes pause, next and previous to the provider', function (string $action, string $method, array $args) {
    $fake = app(FakeMusicProvider::class);
    $party = controlParty();

    $this->postJson(route('api.v1.parties.control', $party), ['action' => $action])->assertOk();

    expect($fake->callsTo($method))->toBe([$args]);
})->with([
    'pause' => ['pause', 'pause', []],
    'next' => ['next', 'next', []],
    'previous' => ['previous', 'seek', [0]],
]);

it('rejects an unsupported control action without calling the provider', function () {
    $fake = app(FakeMusicProvider::class);
    $party = controlParty();

    $this->postJson(route('api.v1.parties.control', $party), ['action' => 'explode'])->assertUnprocessable();

    expect($fake->callsTo('play'))->toBeEmpty()
        ->and($fake->callsTo('pause'))->toBeEmpty()
        ->and($fake->callsTo('next'))->toBeEmpty();
});

it('refuses control from a user who cannot manage the party', function () {
    $fake = app(FakeMusicProvider::class);
    $party = Party::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(route('api.v1.parties.control', $party), ['action' => 'pause'])->assertForbidden();

    expect($fake->callsTo('pause'))->toBeEmpty();
});

it('makes no provider calls when a party is created or saved with a device', function () {
    $fake = app(FakeMusicProvider::class);

    $party = Party::factory()->create(['active' => true]);
    $party->device_id = 'dev1';
    $party->device_name = 'Dev One';
    $party->save();

    expect($fake->calls())->toBeEmpty();
});

it('dispatches PartyUpdate when a party is created through the web route and resolves the device name', function () {
    Queue::fake();
    $fake = app(FakeMusicProvider::class)->withDevices([new Device('dev1', 'Dev One', 'Speaker', true)]);
    $user = User::factory()->create(['first_login' => false]);
    $user->roles()->attach(Role::query()->forceCreate(['code' => 'create-party', 'name' => 'Create Party']));

    $this->actingAs($user)->post(route('parties.store'), [
        'name' => 'New Party',
        'backup_playlist_id' => 'other',
        'custom_backup_playlist_id' => 'backup',
        'device_id' => 'dev1',
    ]);

    $party = Party::query()->where('name', 'New Party')->firstOrFail();
    expect($party->device_name)->toBe('Dev One')
        ->and($party->device_id)->toBe('dev1')
        ->and($fake->callsTo('devices'))->not->toBeEmpty();
    Queue::assertPushed(PartyUpdate::class, 1);
});

it('re-resolves the device name when the device changes on update', function () {
    $fake = app(FakeMusicProvider::class)->withDevices([new Device('dev2', 'Dev Two', 'Speaker', true)]);
    $party = Party::factory()->create(['device_id' => 'dev1', 'device_name' => 'Dev One']);

    $party->user->forceFill(['first_login' => false])->save();

    $this->actingAs($party->user)->put(route('parties.update', $party), [
        'name' => $party->name,
        'backup_playlist_id' => 'other',
        'custom_backup_playlist_id' => 'backup',
        'device_id' => 'dev2',
    ]);

    $party->refresh();
    expect($party->device_id)->toBe('dev2')
        ->and($party->device_name)->toBe('Dev Two')
        ->and($fake->callsTo('play'))->toBeEmpty();
});

it('sends commands to Encore as Soloist commands when Encore is the Player', function (string $action, array $expected) {
    Event::fake([PlayerCommand::class]);
    $fake = app(FakeMusicProvider::class);
    $party = controlParty(['playback_source' => PlaybackSource::Encore, 'playback_received_at' => now()]);

    $this->postJson(route('api.v1.parties.control', $party), ['action' => $action, 'positionMs' => 4200])->assertOk();

    Event::assertDispatched(PlayerCommand::class, fn (PlayerCommand $e) => $e->player === PlaybackSource::Encore
        && $e->command === $expected
        && $e->broadcastOn()->name === "private-encore.{$party->code}");
    expect($fake->calls())->not->toContain(fn ($c) => in_array($c['method'], ['pause', 'next', 'seek'], true));
})->with([
    ['play', ['type' => 'play']],
    ['pause', ['type' => 'pause']],
    ['next', ['type' => 'next']],
    ['previous', ['type' => 'skip_prev']],
    ['seek', ['type' => 'seek', 'position_ms' => 4200]],
]);

it('sends commands to the Web Player when it is the Player', function () {
    Event::fake([PlayerCommand::class]);
    $fake = app(FakeMusicProvider::class);
    $party = controlParty(['playback_source' => PlaybackSource::WebPlayer, 'playback_received_at' => now()]);

    $this->postJson(route('api.v1.parties.control', $party), ['action' => 'pause'])->assertOk();

    Event::assertDispatched(PlayerCommand::class, fn (PlayerCommand $e) => $e->player === PlaybackSource::WebPlayer
        && $e->broadcastOn()->name === "private-webplayer.{$party->code}");
    expect($fake->callsTo('pause'))->toBeEmpty();
});

it('falls back to ProviderPlayback when no pushed Player is fresh', function () {
    Event::fake([PlayerCommand::class]);
    $fake = app(FakeMusicProvider::class)->withPlayback(playingState('cur'));
    $party = controlParty(['playback_source' => PlaybackSource::Encore, 'playback_received_at' => now()->subHour()]);

    $this->postJson(route('api.v1.parties.control', $party), ['action' => 'seek', 'positionMs' => 5000])->assertOk();

    Event::assertNotDispatched(PlayerCommand::class);
    expect($fake->callsTo('seek'))->toBe([[5000]]);
});

it('requires a position to seek', function () {
    $party = controlParty();

    $this->postJson(route('api.v1.parties.control', $party), ['action' => 'seek'])->assertUnprocessable();
});

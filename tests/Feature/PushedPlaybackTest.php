<?php

use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\Role;
use App\Models\Song;
use App\Models\User;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\PlaybackConnectionStatus;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\PlaybackSync;
use Carbon\CarbonImmutable;
use Database\Seeders\PartyMemberRoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeSpotify;

function pushedParty(array $attributes = []): array
{
    FakeSpotify::install();
    $party = Party::factory()->create(array_merge(['provider_id' => 'fake', 'active' => true, 'poll' => true, 'backup_playlist_id' => 'BACKUP'], $attributes));
    Cache::flush();
    $fake = app(FakeMusicProvider::class);
    foreach (['A', 'B'] as $id) {
        $fake->withTrack(new Track('fake', $id, "Name {$id}", 200000, false, true, new AlbumRef("album-{$id}", 'Album', null), [new ArtistRef('artist1', 'Artist')]));
    }

    return [$party, $fake];
}

function pushedState(string $trackId, PlaybackSource $source, ?CarbonImmutable $sampledAt = null): PlaybackState
{
    return new PlaybackState(true, $trackId, null, 200000, 1000, $sampledAt ?? CarbonImmutable::now(), false, 'off', 'device1', 'Speaker', $source);
}

beforeEach(fn () => $this->freezeTime());

it('carries optional upcoming ids and connection status, empty by default', function () {
    $polled = pushedState('A', PlaybackSource::Polling);
    $pushed = new PlaybackState(true, 'A', null, 1, 0, CarbonImmutable::now(), false, 'off', null, null, PlaybackSource::Encore, ['B'], PlaybackConnectionStatus::LoggedOut);

    expect($polled->upcomingTrackIds)->toBe([])
        ->and($polled->connectionStatus)->toBeNull()
        ->and($pushed->upcomingTrackIds)->toBe(['B'])
        ->and($pushed->connectionStatus)->toBe(PlaybackConnectionStatus::LoggedOut);
});

it('records source and receive time of the applied state', function () {
    [$party] = pushedParty();

    app(PlaybackSync::class)->apply($party, pushedState('A', PlaybackSource::WebPlayer));

    $fresh = $party->fresh();
    expect($fresh->playback_source)->toBe(PlaybackSource::WebPlayer)
        ->and($fresh->playback_received_at->diffInMilliseconds(now(), true))->toBeLessThan(2);
});

it('suppresses polling while pushed state is fresh and resumes once stale', function () {
    [$party, $fake] = pushedParty();
    $fake->withPlayback(pushedState('B', PlaybackSource::Polling));
    app(PlaybackSync::class)->apply($party, pushedState('A', PlaybackSource::Encore));

    $this->travel(Party::PUSHED_PLAYBACK_FRESH_SECONDS - 1)->seconds();
    expect(app(PlaybackSync::class)->sync($party->fresh()))->toBeTrue()
        ->and($fake->callsTo('currentPlayback'))->toBe([]);

    $this->travel(2)->seconds();
    app(PlaybackSync::class)->sync($party->fresh());

    expect($fake->callsTo('currentPlayback'))->toHaveCount(1)
        ->and($party->fresh()->playback_source)->toBe(PlaybackSource::Polling);
});

it('party:fallback skips parties with fresh pushed state and polls them once stale', function () {
    Queue::fake();
    [$party] = pushedParty(['last_updated_at' => now()->subMinutes(5)]);
    $party->forceFill(['playback_source' => PlaybackSource::WebPlayer, 'playback_received_at' => now()->subSeconds(5)])->save();

    $this->artisan('party:fallback')->assertSuccessful();
    Queue::assertNotPushed(PartyUpdate::class);

    $this->travel(Party::PUSHED_PLAYBACK_FRESH_SECONDS + 1)->seconds();
    $this->artisan('party:fallback')->assertSuccessful();
    Queue::assertPushed(PartyUpdate::class, 1);
});

it('does not poll a fresh pushed party when polling is off', function () {
    [$party, $fake] = pushedParty(['poll' => false]);
    app(PlaybackSync::class)->apply($party, pushedState('A', PlaybackSource::WebPlayer));

    app(PlaybackSync::class)->sync($party->fresh());

    expect($fake->callsTo('currentPlayback'))->toBe([]);
});

it('drops a state whose sampledAt is older than the last applied', function () {
    [$party] = pushedParty();
    app(PlaybackSync::class)->apply($party, pushedState('A', PlaybackSource::WebPlayer));

    app(PlaybackSync::class)->apply($party->fresh(), pushedState('B', PlaybackSource::WebPlayer, CarbonImmutable::now()->subSeconds(10)));

    $fresh = $party->fresh();
    expect(Song::find($fresh->song_id)->provider_track_id)->toBe('A')
        ->and($fresh->playback_sampled_at->diffInMilliseconds(now(), true))->toBeLessThan(2);
});

it('lets the latest sampledAt win when two sources send', function () {
    [$party] = pushedParty();
    $sync = app(PlaybackSync::class);

    $sync->apply($party, pushedState('A', PlaybackSource::WebPlayer, CarbonImmutable::now()->subSeconds(5)));
    $sync->apply($party->fresh(), pushedState('B', PlaybackSource::Encore, CarbonImmutable::now()));
    $sync->apply($party->fresh(), pushedState('A', PlaybackSource::WebPlayer, CarbonImmutable::now()->subSeconds(2)));

    $fresh = $party->fresh();
    expect(Song::find($fresh->song_id)->provider_track_id)->toBe('B')
        ->and($fresh->playback_source)->toBe(PlaybackSource::Encore);
});

it('shows the Host the current Player on the Party page but not guests', function () {
    $this->withoutVite();
    $this->seed(PartyMemberRoleSeeder::class);
    $host = User::factory()->create(['first_login' => false]);
    [$party] = pushedParty(['user_id' => $host->id]);
    $party->forceFill(['playback_source' => PlaybackSource::Encore])->save();

    $this->actingAs($host)->get(route('parties.show', $party->code))->assertOk()->assertSee('Player: Encore');
    $this->actingAs(User::factory()->create(['first_login' => false]))->get(route('parties.show', $party->code))->assertOk()->assertDontSee('current-player');
});

it('lets polling take over when Encore reports logged out or an error', function (PlaybackConnectionStatus $status) {
    [$party, $fake] = pushedParty();
    $fake->withPlayback(pushedState('B', PlaybackSource::Polling));
    $state = new PlaybackState(true, 'A', null, 200000, 1000, CarbonImmutable::now(), false, 'off', 'device1', 'Speaker', PlaybackSource::Encore, [], $status);
    app(PlaybackSync::class)->apply($party, $state);

    expect($party->fresh()->hasFreshPushedPlayback())->toBeFalse();
    $this->travel(5)->seconds();
    app(PlaybackSync::class)->sync($party->fresh());

    expect($fake->callsTo('currentPlayback'))->toHaveCount(1);
})->with([PlaybackConnectionStatus::LoggedOut, PlaybackConnectionStatus::Error]);

it('regains fresh pushed state once Encore reports connected again', function () {
    [$party] = pushedParty();
    $make = fn (PlaybackConnectionStatus $s, int $offset) => new PlaybackState(true, 'A', null, 200000, 1000, CarbonImmutable::now()->addSeconds($offset), false, 'off', 'device1', 'Speaker', PlaybackSource::Encore, [], $s);
    app(PlaybackSync::class)->apply($party, $make(PlaybackConnectionStatus::LoggedOut, 0));
    app(PlaybackSync::class)->apply($party->fresh(), $make(PlaybackConnectionStatus::Connected, 1));

    expect($party->fresh()->hasFreshPushedPlayback())->toBeTrue();
});

it('shows relay settings to the operator and the app secret only to admins', function () {
    config(['reverb.apps.apps.0.secret' => 'encore-secret-value']);
    $party = Party::factory()->create();
    $owner = User::factory()->withSpotify()->create(['first_login' => false]);
    $party->forceFill(['user_id' => $owner->id])->save();

    $this->withoutVite()->actingAs($owner)->get(route('parties.edit', $party->code))
        ->assertOk()->assertSee("private-encore.{$party->code}")->assertDontSee('encore-secret-value');

    $owner->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
    $this->withoutVite()->actingAs($owner->fresh())->get(route('parties.edit', $party->code))
        ->assertOk()->assertSee('encore-secret-value');
});

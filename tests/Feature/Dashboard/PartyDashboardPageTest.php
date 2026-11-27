<?php

use App\Events\Party\UpdatedEvent;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Playback\EncoreConnection;
use App\Services\PlaybackSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../Support/DashboardFixtures.php';

beforeEach(function () {
    $this->withoutVite();
    $this->fake = dashboardFake();
    $this->party = dashboardParty();
    $this->url = route('parties.dashboard', $this->party->code);
});

function dashboardControl(mixed $test, ?string $path = null): mixed
{
    $props = null;
    $test->actingAs($test->party->user)->get($test->url)->assertOk()
        ->assertInertia(function (Assert $page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

    return $path === null ? $props : data_get($props, $path);
}

it('lets managers in and keeps everyone else out', function (?string $role, int $status) {
    $user = dashboardUser($this->party, $role);

    $this->actingAs($user)->get($this->url)->assertStatus($status);
})->with([
    'owner member' => ['owner', 200],
    'site admin' => ['admin', 200],
    'vip' => ['vip', 403],
    'user' => ['user', 403],
    'non-member' => [null, 403],
]);

it('lets the creator in and redirects a guest to login', function () {
    $this->actingAs($this->party->user)->get($this->url)->assertOk();
    $this->app['auth']->forgetGuards();
    $this->get($this->url)->assertRedirect();
});

it('renders the dashboard page with every top-level prop', function () {
    $this->actingAs($this->party->user)->get($this->url)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Dashboard')
            ->where('party.code', $this->party->code)
            ->where('party.canManage', true)
            ->where('host.name', 'Kestrel')
            ->where('can.moderate', true)
            ->has('can.control')
            ->has('timezone')
            ->has('playerState')
            ->has('control.playingFrom')
            ->has('control.nowPlaying')
            ->has('control.health')
            ->has('control.start')
            ->has('upNext')
            ->has('upcoming.items')
            ->has('attention.issues')
            ->has('attention.flagged')
            ->has('links')
            ->where('hasFallbackPlaylist', false));
});

it('derives the start state', function (string $scenario, string $state) {
    $this->party->forceFill(['context_playlist_id' => 'ctx1'])->saveQuietly();
    $current = Song::factory()->create(['provider_id' => 'spotify', 'provider_track_id' => 'cur']);
    $this->fake->withTrack(dashboardTrack('cur'));

    match ($scenario) {
        'hidden' => $this->fake->withPlayback(dashboardPlaying('cur', contextUri: 'fake:playlist:ctx1')),
        'outside' => $this->fake->withPlayback(dashboardPlaying('cur', contextUri: 'spotify:user:abc:collection'))->withContextName('spotify:user:abc:collection', 'Liked Songs'),
        'resume' => $this->fake->withPlayback(dashboardPlaying('cur', playing: false, progressMs: 72000, contextUri: 'fake:playlist:ctx1')),
        'no_device' => $this->fake->withDevices([]),
        'restricted' => $this->fake->withDevices([new Device('d1', 'Phone', 'Smartphone', false, true)]),
        'relink' => $this->fake->withRelinkRequired(),
        'premium' => Cache::put("party.{$this->party->id}.premium-required", true, 60),
        'idle' => null,
    };
    if (in_array($scenario, ['resume', 'hidden', 'outside'], true)) {
        $this->party->song()->associate($current)->saveQuietly();
    }

    expect(dashboardControl($this, 'control.start.state'))->toBe($state);
})->with([
    ['hidden', 'hidden'],
    ['outside', 'outside'],
    ['resume', 'resume'],
    ['idle', 'idle'],
    ['no_device', 'no_device'],
    ['restricted', 'no_device'],
    ['relink', 'relink_required'],
    ['premium', 'premium_required'],
]);

it('labels a Liked Songs context with the host name and carries the resume position', function () {
    $current = Song::factory()->create(['provider_id' => 'spotify', 'provider_track_id' => 'cur']);
    $this->party->song()->associate($current)->saveQuietly();
    $this->fake->withTrack(dashboardTrack('cur'))
        ->withPlayback(dashboardPlaying('cur', playing: false, progressMs: 72000, contextUri: 'spotify:user:abc:collection'))
        ->withContextName('spotify:user:abc:collection', 'Liked Songs');

    $control = dashboardControl($this, 'control');

    expect($control['playingFrom'])->toBe(['kind' => 'outside', 'label' => "Kestrel's Liked Songs"])
        ->and($control['start']['state'])->toBe('resume')
        ->and($control['start']['target']['kind'])->toBe('resume')
        ->and($control['start']['target']['positionMs'])->toBe(72000)
        ->and($control['start']['selectedDeviceId'])->toBe('fake-device');
});

it('hides devices when the party is already playing and fetches them otherwise', function () {
    $current = Song::factory()->create(['provider_id' => 'spotify', 'provider_track_id' => 'cur']);
    $this->party->forceFill(['context_playlist_id' => 'ctx1'])->saveQuietly();
    $this->party->song()->associate($current)->saveQuietly();
    $this->fake->withPlayback(dashboardPlaying('cur', contextUri: 'fake:playlist:ctx1'));

    expect(dashboardControl($this, 'control.start.devices'))->toBe([])
        ->and($this->fake->callsTo('devices'))->toBeEmpty();
});

it('shows the sent request first, then the top candidates (Up Next rows included), excluding flagged and played requests', function () {
    $sent = dashboardRequest($this->party, ['queued_at' => now()]);
    $played = dashboardRequest($this->party, ['queued_at' => now()->subMinutes(5)]);
    dashboardPlayed($played);
    dashboardRequest($this->party, ['flagged_at' => now(), 'flag_reason' => 'x', 'score' => 99]);
    $low = dashboardRequest($this->party, ['score' => 1]);
    $high = dashboardRequest($this->party, ['score' => 5]);
    $mid = dashboardRequest($this->party, ['score' => 3]);
    $last = dashboardRequest($this->party, ['score' => 0]);

    $props = dashboardControl($this);

    expect(array_column($props['upNext'], 'id'))->toBe([$sent->id, $high->id, $mid->id])
        ->and(array_column($props['upNext'], 'sent'))->toBe([true, false, false])
        ->and(array_column($props['upcoming']['items'], 'id'))->toBe([$sent->id, $high->id, $mid->id, $low->id, $last->id])
        ->and(array_column($props['upcoming']['items'], 'sent'))->toBe([true, false, false, false, false])
        ->and($props['upcoming']['total'])->toBe(5);
});

it('reports attention issues and caps the flagged list at five', function () {
    $this->party->forceFill(['active' => false, 'playback_source' => PlaybackSource::Encore, 'playback_received_at' => now()->subMinutes(5)])->saveQuietly();
    $this->fake->withRelinkRequired();
    foreach (range(1, 7) as $i) {
        dashboardRequest($this->party, ['flagged_at' => now()->addSeconds($i), 'flag_reason' => 'x']);
    }

    $attention = dashboardControl($this, 'attention');

    expect(array_column($attention['issues'], 'code'))->toBe(['relink_required', 'party_inactive', 'player_stale'])
        ->and($attention['issues'][1]['action'])->toBe(['label' => 'Activate', 'kind' => 'activate'])
        ->and($attention['flagged'])->toHaveCount(5)
        ->and($attention['flaggedTotal'])->toBe(7);
});

it('does not flag the player as stale while Encore is connected', function (?bool $connected, bool $flagged) {
    $this->app->instance(EncoreConnection::class, new class($connected) extends EncoreConnection
    {
        public function __construct(private readonly ?bool $answer) {}

        public function isConnected(Party $party): ?bool
        {
            return $this->answer;
        }
    });
    $this->party->forceFill(['poll' => true, 'playback_source' => PlaybackSource::Encore, 'playback_received_at' => now()->subMinutes(5)])->saveQuietly();

    $props = dashboardControl($this);

    expect(array_column($props['attention']['issues'], 'code') === ['player_stale'])->toBe($flagged)
        ->and($props['control']['health']['player']['connected'])->toBe($connected)
        ->and($props['control']['health']['player']['fresh'])->toBe($connected === true)
        ->and($props['control']['health']['polling'])->toBe($connected === true ? 'backup' : 'on');
})->with([[true, false], [false, true], [null, true]]);

it('says polling is off in the stale detail when polling is disabled', function (bool $poll, string $detail) {
    $this->party->forceFill(['poll' => $poll, 'playback_source' => PlaybackSource::Encore, 'playback_received_at' => now()->subMinutes(5)])->saveQuietly();

    $issues = dashboardControl($this, 'attention')['issues'];

    expect($issues[0]['detail'])->toBe($detail);
})->with([[true, 'Polling is checking Spotify'], [false, 'Polling is off']]);

it('flags a site admin who is not a member as unable to moderate', function () {
    $admin = dashboardUser($this->party, 'admin');

    $this->actingAs($admin)->get($this->url)->assertInertia(fn (Assert $page) => $page
        ->where('can.control', true)
        ->where('can.moderate', false)
        ->where('party.canManage', false));
});

it('only links the web player to those who can use it', function () {
    expect(dashboardControl($this, 'links.webPlayer'))->toBeNull();

    $owner = dashboardUser($this->party, 'owner');
    $this->actingAs($owner)->get($this->url)->assertInertia(fn (Assert $page) => $page
        ->where('links.webPlayer', route('parties.player', $this->party->code))
        ->where('links.encoreRelay', route('parties.edit', $this->party->code).'#encore-relay'));
});

it('does not touch the provider devices on a partial reload of upNext', function () {
    dashboardRequest($this->party);

    $version = null;
    $this->actingAs($this->party->user)->get($this->url)->assertInertia(function (Assert $page) use (&$version): void {
        $version = $page->toArray()['version'];
    });
    app()->forgetInstance(FakeMusicProvider::class);

    $this->get($this->url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'Parties/Dashboard',
        'X-Inertia-Partial-Data' => 'upNext',
    ])->assertOk()->assertJsonCount(1, 'props.upNext')->assertJsonMissingPath('props.control');

    expect(dashboardFake()->callsTo('devices'))->toBeEmpty();
});

it('treats Encore as connected and not stale while its activity signal is fresh, even when paused', function () {
    $this->party->forceFill(['poll' => true, 'playback_source' => PlaybackSource::Encore, 'playback_received_at' => now()->subMinutes(10)])->saveQuietly();
    Cache::put(EncoreConnection::seenKey($this->party->code), time(), EncoreConnection::seenSeconds());

    $props = dashboardControl($this);

    expect($props['attention']['issues'])->toBe([])
        ->and($props['control']['health']['player']['connected'])->toBeTrue()
        ->and($props['control']['health']['player']['fresh'])->toBeTrue()
        ->and($props['control']['health']['polling'])->toBe('backup');
});

it('warns again once the Encore activity signal expires', function () {
    config(['broadcasting.default' => 'null']);
    $this->party->forceFill(['poll' => true, 'playback_source' => PlaybackSource::Encore, 'playback_received_at' => now()->subMinutes(10)])->saveQuietly();
    Cache::put(EncoreConnection::seenKey($this->party->code), time(), EncoreConnection::seenSeconds());
    $this->travel(EncoreConnection::seenSeconds() + 1)->seconds();

    $props = dashboardControl($this);

    expect(array_column($props['attention']['issues'], 'code'))->toBe(['player_stale'])
        ->and($props['control']['health']['player']['connected'])->toBeNull();
});

it('reports a newer receivedAt after an unchanged Encore frame, which broadcasts nothing', function () {
    Event::fake([UpdatedEvent::class]);
    $this->party->forceFill(['playback_source' => PlaybackSource::Encore, 'playback_received_at' => null])->saveQuietly();
    $state = PlaybackState::idle(PlaybackSource::Encore);
    $sync = app(PlaybackSync::class);

    $sync->apply($this->party->fresh(), $state);
    $first = dashboardControl($this, 'control.health.receivedAt');
    $this->travel(20)->seconds();
    $sync->apply($this->party->fresh(), $state);
    $second = dashboardControl($this, 'control.health.receivedAt');

    expect($second)->toBeGreaterThan($first);
    Event::assertDispatchedTimes(UpdatedEvent::class, 1);
});

it('passes myRating for the viewer on the current played song, null when nothing is playing', function () {
    expect(dashboardControl($this, 'myRating'))->toBeNull();

    $played = PlayedSong::factory()->for($this->party)->create(['played_at' => now()]);
    expect(dashboardControl($this, 'myRating'))->toBe(['playedSongId' => $played->id, 'value' => 0]);

    $played->setRating($this->party->user, 1);
    expect(dashboardControl($this, 'myRating'))->toBe(['playedSongId' => $played->id, 'value' => 1]);
});

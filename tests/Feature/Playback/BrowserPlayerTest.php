<?php

use App\Domain\Playback\Actions\ClaimBrowserPlayer;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\FeedMode;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Playback\Players\BrowserPlayer;
use App\Domain\Queue\RequestStatus;
use App\Events\Player\BrowserPlayerCommandEvent;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use App\Models\SocialProvider;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->host = User::factory()->create();
    $this->party = Party::factory()->live()->create(['user_id' => $this->host->id, 'music_provider' => 'spotify', 'player_kind' => 'browser']);
    $provider = SocialProvider::factory()->create(['code' => 'spotify']);
    $this->account = LinkedAccount::factory()->for($this->host)->create(['social_provider_id' => $provider->id, 'access_token' => 'secret-host-token']);
});

function browserPlayerFor(Party $party): BrowserPlayer
{
    $player = app(PartyPlayers::class)->for($party);
    assert($player instanceof BrowserPlayer);

    return $player;
}

it('is a just-in-time player that needs a host account', function () {
    $player = browserPlayerFor($this->party);

    expect($player->feedMode())->toBe(FeedMode::JustInTime)
        ->and($player->requiresHostAccount())->toBeTrue()
        ->and($player->kind())->toBe('browser');
});

it('delivers the host token by inertia prop with no-store caching', function () {
    $this->withoutVite()->actingAs($this->host)->get(route('parties.player.show', $this->party))
        ->assertOk()
        ->assertHeader('Cache-Control')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Party/BrowserPlayer')
            ->where('accessToken', 'secret-host-token')
            ->where('error', null)
            ->where('channel', 'party.'.$this->party->code.'.browser-player')
            ->where('leadSeconds', (int) config('musicparty.just_in_time_lead_seconds'))
            ->where('party', ['code' => $this->party->code, 'name' => $this->party->name]));

    expect($this->withoutVite()->actingAs($this->host)->get(route('parties.player.show', $this->party))->headers->get('Cache-Control'))
        ->toContain('no-store');
});

it('refuses the player page to everyone but the host', function (string $role) {
    $user = User::factory()->create();
    $member = PartyMember::factory()->for($this->party)->for($user);
    $role === 'none' ?: ($role === 'moderator' ? $member->moderator()->create() : $member->create());

    $this->withoutVite()->actingAs($user)->get(route('parties.player.show', $this->party))->assertForbidden();
})->with(['guest' => 'guest', 'moderator' => 'moderator', 'non-member' => 'none']);

it('redirects an anonymous visitor away from the player page', function () {
    $this->get(route('parties.player.show', $this->party))->assertRedirect();
});

it('withholds the token when the party is not using the browser player', function () {
    $this->party->forceFill(['player_kind' => 'fake'])->save();

    $this->withoutVite()->actingAs($this->host)->get(route('parties.player.show', $this->party))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('accessToken', null)->where('error', 'This Party is not using the Browser Player.'));
});

it('withholds the token when no music account is linked', function () {
    $this->account->delete();

    $this->withoutVite()->actingAs($this->host)->get(route('parties.player.show', $this->party))
        ->assertInertia(fn (Assert $page): Assert => $page->where('accessToken', null)->whereType('error', 'string'));
});

it('withholds the token when the account needs relinking', function () {
    $this->account->forceFill(['needs_relink' => true])->save();

    $this->withoutVite()->actingAs($this->host)->get(route('parties.player.show', $this->party))
        ->assertInertia(fn (Assert $page): Assert => $page->where('accessToken', null)->whereType('error', 'string'));
});

it('never puts the token on the party page or in broadcasts', function () {
    PartyMember::factory()->for($this->party)->for($this->host)->create();

    $this->withoutVite()->actingAs($this->host)->get(route('parties.show', $this->party))
        ->assertOk()
        ->assertDontSee('secret-host-token', false);

    $event = new BrowserPlayerCommandEvent($this->party->code, 'spotify', 'track-1');
    $channels = array_map(fn ($channel): string => $channel->name, $event->broadcastOn());

    expect(json_encode($event->broadcastWith()))->not->toContain('secret-host-token')
        ->and($channels)->toBe(['private-party.'.$this->party->code.'.browser-player'])
        ->and($event->broadcastWith())->toBe(['action' => 'play', 'provider_id' => 'spotify', 'track_id' => 'track-1']);
});

it('authorises the host private channel and refuses others', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
    require base_path('routes/channels.php');
    $channel = 'private-party.'.$this->party->code.'.browser-player';

    $this->actingAs($this->host)->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])->assertOk();

    $other = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($other)->moderator()->create();
    $this->actingAs($other)->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])->assertForbidden();
});

it('lets only one tab hold the player role', function () {
    $this->actingAs($this->host);

    $this->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();
    $this->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();
    $this->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-b'])->assertStatus(409)->assertJsonStructure(['message']);
});

it('requires a tab id and refuses non-hosts when claiming', function () {
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), [])->assertUnprocessable();

    $this->actingAs(User::factory()->create())->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertForbidden();
});

it('refuses to claim when the party is not using the browser player', function () {
    $this->party->forceFill(['player_kind' => 'fake'])->save();

    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertStatus(409);
});

it('records reported state and hands a changed track to the coordinator', function () {
    $coordinator = Mockery::mock(PlaybackCoordinator::class);
    $coordinator->shouldReceive('trackChanged')->once()->withArgs(fn (Party $party, string $track): bool => $track === 'track-1');
    app()->instance(PlaybackCoordinator::class, $coordinator);
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();

    $this->postJson(route('parties.player.report', $this->party), ['tab_id' => 'tab-a', 'status' => 'playing', 'track_id' => 'track-1', 'position_ms' => 1000, 'duration_ms' => 200000])->assertOk();
    $this->postJson(route('parties.player.report', $this->party), ['tab_id' => 'tab-a', 'status' => 'playing', 'track_id' => 'track-1', 'position_ms' => 6000, 'duration_ms' => 200000])->assertOk();

    $state = browserPlayerFor($this->party)->state();
    expect($state->status)->toBe(PlaybackStatus::Playing)
        ->and($state->currentTrack?->providerTrackId)->toBe('track-1')
        ->and($state->positionMs)->toBe(6000)
        ->and($state->durationMs)->toBe(200000);
});

it('tells the coordinator when playback stops', function () {
    $coordinator = Mockery::mock(PlaybackCoordinator::class);
    $coordinator->shouldReceive('playbackEnded')->once();
    app()->instance(PlaybackCoordinator::class, $coordinator);
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();

    $this->postJson(route('parties.player.report', $this->party), ['tab_id' => 'tab-a', 'status' => 'stopped', 'track_id' => null, 'position_ms' => 0])->assertOk();
});

it('refuses state from a tab without the claim', function () {
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();

    $this->postJson(route('parties.player.report', $this->party), ['tab_id' => 'tab-b', 'status' => 'playing', 'track_id' => 'track-1', 'position_ms' => 0])->assertStatus(409);

    expect(browserPlayerFor($this->party)->state()->status)->toBe(PlaybackStatus::Stopped);
});

it('validates reported state', function (array $payload) {
    $this->actingAs($this->host)->postJson(route('parties.player.report', $this->party), $payload)->assertUnprocessable();
})->with([
    'no status' => [['tab_id' => 'a', 'position_ms' => 0]],
    'bad status' => [['tab_id' => 'a', 'status' => 'dancing', 'position_ms' => 0]],
    'negative position' => [['tab_id' => 'a', 'status' => 'playing', 'position_ms' => -1]],
    'no tab' => [['status' => 'playing', 'position_ms' => 0]],
]);

it('releases the role, stops playback and writes a party log entry', function () {
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();
    $this->postJson(route('parties.player.report', $this->party), ['tab_id' => 'tab-a', 'status' => 'playing', 'track_id' => 'track-1', 'position_ms' => 10])->assertOk();

    $this->postJson(route('parties.player.release', $this->party), ['tab_id' => 'tab-a'])->assertOk()->assertJson(['released' => true]);

    expect(browserPlayerFor($this->party)->isConnected())->toBeFalse()
        ->and(browserPlayerFor($this->party)->state()->status)->toBe(PlaybackStatus::Stopped)
        ->and(PartyLogEntry::query()->where('party_id', $this->party->id)->where('action', 'player.disconnected')->count())->toBe(1);

    $this->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-b'])->assertOk();
});

it('ignores a release from a tab that is not the player', function () {
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();

    $this->postJson(route('parties.player.release', $this->party), ['tab_id' => 'tab-b'])->assertOk()->assertJson(['released' => false]);

    expect(browserPlayerFor($this->party)->isConnected())->toBeTrue()
        ->and(PartyLogEntry::query()->where('action', 'player.disconnected')->count())->toBe(0);
});

it('frees an abandoned claim once it expires', function () {
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();

    $this->travel(181)->seconds();

    $this->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-b'])->assertOk();
});

it('refuses to enqueue when no tab holds the role', function () {
    Event::fake([BrowserPlayerCommandEvent::class]);

    expect(fn () => browserPlayerFor($this->party)->enqueue('spotify', 'track-1'))->toThrow(PlayerDisconnectedException::class);

    Event::assertNotDispatched(BrowserPlayerCommandEvent::class);
});

it('broadcasts a play command to the host channel when a tab holds the role', function () {
    Event::fake([BrowserPlayerCommandEvent::class]);
    browserPlayerFor($this->party)->claim('tab-a');

    browserPlayerFor($this->party)->enqueue('spotify', 'track-1');

    Event::assertDispatched(BrowserPlayerCommandEvent::class, fn (BrowserPlayerCommandEvent $event): bool => $event->broadcastWith()['track_id'] === 'track-1');
});

it('never broadcasts the token on any channel through a full playback flow', function () {
    $broadcast = [];
    Event::listen('*', function (string $name, array $payload) use (&$broadcast): void {
        $event = $payload[0] ?? null;

        if ($event instanceof ShouldBroadcast) {
            $broadcast[] = json_encode([$name, $event->broadcastWith(), array_map(fn ($channel): string => $channel->name, (array) $event->broadcastOn())]);
        }
    });

    $this->actingAs($this->host)->get(route('parties.player.show', $this->party));
    $this->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a']);
    browserPlayerFor($this->party)->enqueue('spotify', 'track-1');
    $this->postJson(route('parties.player.report', $this->party), ['tab_id' => 'tab-a', 'status' => 'playing', 'track_id' => 'track-1', 'position_ms' => 0]);
    $this->postJson(route('parties.player.release', $this->party), ['tab_id' => 'tab-a']);

    expect($broadcast)->not->toBeEmpty()
        ->and(implode('', $broadcast))->not->toContain('secret-host-token');
});

it('keeps the role across a long pause when the tab heartbeats', function () {
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();

    $this->travel(120)->seconds();
    $this->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();
    $this->travel(120)->seconds();

    expect(browserPlayerFor($this->party)->holds('tab-a'))->toBeTrue();
    browserPlayerFor($this->party)->enqueue('spotify', 'track-1');
});

it('lets a tab recover its expired role by reporting state', function () {
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();
    $this->travel(181)->seconds();

    $this->postJson(route('parties.player.report', $this->party), ['tab_id' => 'tab-a', 'status' => 'paused', 'track_id' => null, 'position_ms' => 0])->assertOk();

    expect(browserPlayerFor($this->party)->holds('tab-a'))->toBeTrue();
});

it('does not let a report steal the role from another tab', function () {
    $this->actingAs($this->host)->postJson(route('parties.player.claim', $this->party), ['tab_id' => 'tab-a'])->assertOk();

    $this->postJson(route('parties.player.report', $this->party), ['tab_id' => 'tab-b', 'status' => 'paused', 'track_id' => null, 'position_ms' => 0])->assertStatus(409);
});

it('retries a failed enqueue at once when a tab claims the player', function () {
    Event::fake([BrowserPlayerCommandEvent::class]);
    $request = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::UpNext]);
    $coordinator = app(PlaybackCoordinator::class);

    $coordinator->tick($this->party);
    $coordinator->tick($this->party);

    expect($request->fresh()->enqueued_at)->toBeNull();

    app(ClaimBrowserPlayer::class)($this->party, 'tab-a');
    $coordinator->tick($this->party);

    expect($request->fresh()->enqueued_at)->not->toBeNull();
});

it('yields exactly one holder when two tabs claim at once', function () {
    $player = browserPlayerFor($this->party);

    $results = [$player->claim('tab-a'), $player->claim('tab-b')];

    expect($results)->toBe([true, false])
        ->and($player->holds('tab-a'))->toBeTrue()
        ->and($player->holds('tab-b'))->toBeFalse();
});

it('keeps the first holder when the second claim races past the empty check', function () {
    $player = browserPlayerFor($this->party);
    Cache::add("playback.browser.{$this->party->code}.claim", 'tab-a', 180);

    expect($player->claim('tab-b'))->toBeFalse()
        ->and($player->claim('tab-a'))->toBeTrue()
        ->and($player->holds('tab-a'))->toBeTrue();
});

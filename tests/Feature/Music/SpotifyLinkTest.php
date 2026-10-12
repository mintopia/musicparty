<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\SocialProviders\SpotifyProvider;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Party\PartyState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

const SPOTIFY_ALL_SCOPES = 'playlist-read-private playlist-read-collaborative playlist-modify-private playlist-modify-public user-read-playback-state user-modify-playback-state';

function spotifyLinkProvider(): SocialProvider
{
    $provider = SocialProvider::query()->where('code', 'spotify')->first();

    if ($provider === null) {
        $provider = new SocialProvider;
        $provider->forceFill(['code' => 'spotify', 'name' => 'Spotify', 'provider_class' => SpotifyProvider::class])->save();
    }

    return $provider;
}

function spotifyLinkLinked(User $user, ?string $scopes = SPOTIFY_ALL_SCOPES, bool $needsRelink = false): LinkedAccount
{
    return LinkedAccount::factory()->for($user)->create([
        'social_provider_id' => spotifyLinkProvider()->id,
        'scopes' => $scopes,
        'needs_relink' => $needsRelink,
        'name' => 'Spotty',
    ]);
}

function mockSpotifyLinkCallback(array $approved = []): void
{
    $remote = (new SocialiteUser)->map(['id' => 'sp-1', 'name' => 'Host Name', 'nickname' => null, 'email' => null, 'avatar' => null]);
    $remote->token = 'plain-access-token';
    $remote->refreshToken = 'plain-refresh-token';
    $remote->expiresIn = 3600;
    $remote->approvedScopes = $approved;

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('user')->andReturn($remote);
    $driver->shouldReceive('scopes')->andReturnSelf();
    Socialite::shouldReceive('buildProvider')->andReturn($driver);
}

it('requests all six scopes when starting the control link', function () {
    $user = User::factory()->withRole('create-party')->create();

    $location = urldecode($this->actingAs($user)->get(route('spotify.link', 'control'))->assertRedirect()->headers->get('Location'));

    expect($location)->toContain('accounts.spotify.com');
    foreach (explode(' ', SPOTIFY_ALL_SCOPES) as $scope) {
        expect($location)->toContain($scope);
    }
});

it('stores scopes and returns to a valid return path', function () {
    spotifyLinkProvider();
    $user = User::factory()->withRole('create-party')->create();
    mockSpotifyLinkCallback(explode(' ', SPOTIFY_ALL_SCOPES));

    $this->actingAs($user)->withSession(['spotify_link_return' => '/parties/ABC/settings'])
        ->get(route('spotify.link.return'))->assertRedirect('/parties/ABC/settings');

    $account = LinkedAccount::query()->where('user_id', $user->id)->firstOrFail();
    expect($account->scopes)->toBe(SPOTIFY_ALL_SCOPES)->and($account->needs_relink)->toBeFalse();
});

it('keeps the return query parameter in the session when starting the link', function () {
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->get(route('spotify.link', ['account' => 'control', 'return' => '/spotify']))->assertRedirect();

    expect(session('spotify_link_return'))->toBe('/spotify');
});

it('falls back to the spotify page for unsafe return values', function (?string $return) {
    spotifyLinkProvider();
    $user = User::factory()->withRole('create-party')->create();
    mockSpotifyLinkCallback();

    $this->actingAs($user)->withSession(['spotify_link_return' => $return])
        ->get(route('spotify.link.return'))->assertRedirect('/spotify');
})->with(['protocol relative' => '//evil.example', 'backslash' => '/\\evil.example', 'absolute' => 'https://evil.example', 'none' => null, 'no slash' => 'evil']);

it('refuses a user who cannot create or host parties', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('spotify.index'))->assertForbidden();
    $this->actingAs($user)->get(route('spotify.link', 'control'))->assertForbidden();
    $this->actingAs($user)->delete(route('spotify.unlink', 'control'))->assertForbidden();
});

it('allows the host of any party without the create-party role', function () {
    $user = User::factory()->create();
    Party::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('spotify.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Spotify/Link')->where('can_create_party', false));
});

it('shows link state and urls on the page', function () {
    $user = User::factory()->withRole('create-party')->create();
    spotifyLinkLinked($user);

    $this->actingAs($user)->get(route('spotify.index'))->assertInertia(fn ($page) => $page
        ->component('Spotify/Link')
        ->where('control', ['linked' => true, 'name' => 'Spotty', 'needs_relink' => false])
        ->where('can_create_party', true)
        ->where('link_url', route('spotify.link', ['account' => 'control', 'return' => '/spotify']))
        ->where('unlink_url', route('spotify.unlink', 'control'))
        ->where('create_party_url', route('parties.create')));
});

it('reports an unlinked account', function () {
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->get(route('spotify.index'))->assertInertia(fn ($page) => $page
        ->where('control', ['linked' => false, 'name' => null, 'needs_relink' => false]));
});

it('reports an old link without playback scopes as needing relink', function (?string $scopes) {
    $user = User::factory()->withRole('create-party')->create();
    spotifyLinkLinked($user, $scopes);

    $this->actingAs($user)->get(route('spotify.index'))->assertInertia(fn ($page) => $page->where('control.needs_relink', true));
})->with(['null' => null, 'playlist only' => 'playlist-read-private playlist-modify-private']);

it('unlinks and pauses live polling parties with a party log entry', function () {
    $user = User::factory()->withRole('create-party')->create();
    spotifyLinkLinked($user);
    LinkedAccount::factory()->for($user)->create(['social_provider_id' => SocialProvider::factory()->create()->id]);
    $other = User::factory()->create();
    $polling = Party::factory()->live()->create(['user_id' => $user->id, 'player_kind' => 'polling']);
    $fake = Party::factory()->live()->create(['user_id' => $user->id, 'player_kind' => 'fake']);
    $theirs = Party::factory()->live()->create(['user_id' => $other->id, 'player_kind' => 'polling']);

    $this->actingAs($user)->delete(route('spotify.unlink', 'control'))->assertRedirect(route('spotify.index'));

    expect(LinkedAccount::query()->where('user_id', $user->id)->where('social_provider_id', spotifyLinkProvider()->id)->exists())->toBeFalse()
        ->and($polling->fresh()->state)->toBe(PartyState::Paused)
        ->and($fake->fresh()->state)->toBe(PartyState::Live)
        ->and($theirs->fresh()->state)->toBe(PartyState::Live)
        ->and(PartyLogEntry::query()->where('party_id', $polling->id)->where('action', 'party.paused')->first()->details['reason'])->toBe('spotify_unlinked')
        ->and(PartyLogEntry::query()->where('party_id', $polling->id)->where('action', 'player.host_account_unlinked')->exists())->toBeTrue();

    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('flash.success', 'Spotify account unlinked'));
});

it('exposes link status and unlink through the api with the same authorisation', function () {
    $user = User::factory()->withRole('create-party')->create();
    spotifyLinkLinked($user, null);
    $party = Party::factory()->live()->create(['user_id' => $user->id, 'player_kind' => 'polling']);
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.spotify.link.show'))->assertOk()
        ->assertJsonPath('data', ['linked' => true, 'name' => 'Spotty', 'needs_relink' => true]);

    $this->deleteJson(route('api.v1.spotify.link.destroy'))->assertNoContent();

    $this->getJson(route('api.v1.spotify.link.show'))->assertJsonPath('data', ['linked' => false, 'name' => null, 'needs_relink' => false]);
    expect($party->fresh()->state)->toBe(PartyState::Paused);
});

it('refuses api access to users who cannot create or host parties', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.v1.spotify.link.show'))->assertForbidden();
    $this->deleteJson(route('api.v1.spotify.link.destroy'))->assertForbidden();
});

it('shares the spotify link url on create and settings and flags a missing link to the host', function () {
    $host = User::factory()->withRole('create-party')->create();
    $party = Party::factory()->create(['user_id' => $host->id, 'player_kind' => 'polling']);
    PartyMember::factory()->host()->create(['party_id' => $party->id, 'user_id' => $host->id]);

    $this->actingAs($host)->get(route('parties.create'))->assertInertia(fn ($page) => $page->where('spotify_link_url', route('spotify.index', ['return' => route('parties.create', absolute: false)])));
    $this->get(route('parties.settings', $party->code))->assertInertia(fn ($page) => $page->where('spotify_link_url', route('spotify.index', ['return' => route('parties.settings', $party->code, false)])));
    $this->get(route('parties.show', $party->code))->assertInertia(fn ($page) => $page->where('spotify_link_needed', true)->where('spotify_link_url', route('spotify.index', ['return' => route('parties.show', $party->code, false)])));

    spotifyLinkLinked($host);
    $this->get(route('parties.show', $party->code))->assertInertia(fn ($page) => $page->where('spotify_link_needed', false));
});

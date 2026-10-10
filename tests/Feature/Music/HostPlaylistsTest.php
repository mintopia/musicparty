<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\SocialProviders\SpotifyProvider;
use App\Domain\Music\Actions\AppendPlayToHistory;
use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Data\PlaylistData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Jobs\AppendToHistoryPlaylist;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Actions\AdvanceQueue;
use App\Domain\Queue\Actions\TopUpFallbackRequests;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function makeUser(): User
{
    $user = new User;
    $user->forceFill(['nickname' => fake()->unique()->userName(), 'first_login' => false, 'terms_agreed_at' => now()]);
    $user->save();

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeHostedParty(User $host, array $attributes = []): Party
{
    $party = new Party;
    $party->forceFill(array_merge(['code' => strtoupper(fake()->unique()->lexify('????????')), 'name' => 'Test Party', 'user_id' => $host->id, 'music_provider' => 'fake'], $attributes));
    Party::withoutEvents(fn () => $party->save());

    return $party;
}

function spotifyProvider(string $code = 'spotify'): SocialProvider
{
    $provider = SocialProvider::query()->where('code', $code)->first();
    if ($provider === null) {
        $provider = new SocialProvider;
        $provider->forceFill(['code' => $code, 'name' => ucfirst($code), 'provider_class' => SpotifyProvider::class])->save();
    }

    return $provider;
}

/**
 * @return array{User, LinkedAccount}
 */
function hostWithAccount(): array
{
    $host = makeUser();
    $account = LinkedAccount::factory()->for($host)->create(['social_provider_id' => spotifyProvider('fake')->id]);

    return [$host, $account];
}

beforeEach(function () {
    Party::flushEventListeners();
    $this->fake = FakeMusicProvider::withDefaultCatalogue();
    $this->app->instance(FakeMusicProvider::class, $this->fake);
});

it('lists the host playlists without tokens', function () {
    [$host, $account] = hostWithAccount();
    $party = makeHostedParty($host);
    Sanctum::actingAs($host);

    $response = $this->getJson(route('api.v1.parties.playlists.index', $party))->assertOk();

    expect($response->json('data'))->toBe([['id' => 'playlist-1', 'name' => 'Fallback Mix', 'track_count' => 2]])
        ->and($response->getContent())->not->toContain($account->access_token, 'access_token', 'refresh_token');
});

it('refuses non-hosts listing and selecting playlists', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host);
    Sanctum::actingAs(makeUser());

    $this->getJson(route('api.v1.parties.playlists.index', $party))->assertForbidden();
    $this->putJson(route('api.v1.parties.playlists.update', $party), ['fallback_playlist_id' => null, 'history_playlist_id' => null])->assertForbidden();
});

it('requires authentication', function () {
    [$host] = hostWithAccount();

    $this->getJson(route('api.v1.parties.playlists.index', makeHostedParty($host)))->assertUnauthorized();
});

it('persists selected playlists and clears them with null', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host);
    Sanctum::actingAs($host);

    $this->putJson(route('api.v1.parties.playlists.update', $party), ['fallback_playlist_id' => 'playlist-1', 'history_playlist_id' => 'playlist-1'])
        ->assertOk()
        ->assertExactJson(['data' => ['fallback_playlist_id' => 'playlist-1', 'history_playlist_id' => 'playlist-1']]);

    expect($party->fresh()->fallback_playlist_id)->toBe('playlist-1');

    $this->putJson(route('api.v1.parties.playlists.update', $party), ['fallback_playlist_id' => null, 'history_playlist_id' => null])->assertOk();

    expect($party->fresh()->fallback_playlist_id)->toBeNull()
        ->and($party->fresh()->history_playlist_id)->toBeNull();
});

it('rejects playlists that do not belong to the host', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host);
    Sanctum::actingAs($host);

    $this->putJson(route('api.v1.parties.playlists.update', $party), ['fallback_playlist_id' => 'foreign', 'history_playlist_id' => 'foreign-2'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fallback_playlist_id', 'history_playlist_id']);

    expect($party->fresh()->fallback_playlist_id)->toBeNull();
});

it('rejects a history playlist when the provider cannot write', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host);
    $this->app->instance(FakeMusicProvider::class, new FakeMusicProvider(playlists: [new PlaylistData('playlist-1', 'Mix')], capabilities: []));
    Sanctum::actingAs($host);

    $this->putJson(route('api.v1.parties.playlists.update', $party), ['fallback_playlist_id' => null, 'history_playlist_id' => 'playlist-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['history_playlist_id']);
});

it('requires both playlist keys', function () {
    [$host] = hostWithAccount();
    Sanctum::actingAs($host);

    $this->putJson(route('api.v1.parties.playlists.update', makeHostedParty($host)), [])->assertUnprocessable()
        ->assertJsonValidationErrors(['fallback_playlist_id', 'history_playlist_id']);
});

it('redirects the host to spotify with the playlist scopes', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host);

    $response = $this->actingAs($host)->get(route('spotify.link', $party))->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toContain('accounts.spotify.com')
        ->and(urldecode($location))->toContain('playlist-modify-private')
        ->and($location)->toContain(urlencode(route('spotify.link.return')));
});

it('refuses non-hosts starting the link flow', function () {
    [$host] = hostWithAccount();

    $this->actingAs(makeUser())->get(route('spotify.link', makeHostedParty($host)))->assertForbidden();
});

function mockSpotifyCallback(): void
{
    $remote = (new SocialiteUser)->map(['id' => 'spotify-user-1', 'name' => 'Host Name', 'nickname' => null, 'email' => null, 'avatar' => null]);
    $remote->token = 'plain-access-token';
    $remote->refreshToken = 'plain-refresh-token';
    $remote->expiresIn = 3600;

    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('user')->andReturn($remote);
    $driver->shouldReceive('scopes')->andReturnSelf();
    Socialite::shouldReceive('buildProvider')->andReturn($driver);
}

it('links the host account with encrypted tokens', function () {
    spotifyProvider();
    $host = makeUser();
    $party = makeHostedParty($host);
    mockSpotifyCallback();

    $this->actingAs($host)->withSession(['spotify_link_party_id' => $party->id])
        ->get(route('spotify.link.return'))->assertRedirect(route('home'));

    $account = LinkedAccount::query()->where('user_id', $host->id)->firstOrFail();
    $raw = DB::table('linked_accounts')->where('id', $account->id)->first();

    expect($account->access_token)->toBe('plain-access-token')
        ->and($account->refresh_token)->toBe('plain-refresh-token')
        ->and($account->needs_relink)->toBeFalse()
        ->and($raw->access_token)->not->toBe('plain-access-token')
        ->and($raw->refresh_token)->not->toBe('plain-refresh-token')
        ->and($account->external_id)->toBe('spotify-user-1');
});

it('clears needs_relink when re-linking and does not duplicate the account', function () {
    $host = makeUser();
    LinkedAccount::factory()->for($host)->needingRelink()->create(['social_provider_id' => spotifyProvider()->id]);
    $party = makeHostedParty($host);
    mockSpotifyCallback();

    $this->actingAs($host)->withSession(['spotify_link_party_id' => $party->id])->get(route('spotify.link.return'))->assertRedirect();

    expect(LinkedAccount::query()->where('user_id', $host->id)->count())->toBe(1)
        ->and(LinkedAccount::query()->where('user_id', $host->id)->first()->needs_relink)->toBeFalse();
});

it('refuses linking by a non-host at callback', function () {
    spotifyProvider();
    $host = makeUser();
    $party = makeHostedParty($host);
    $stranger = makeUser();
    mockSpotifyCallback();

    $this->actingAs($stranger)->withSession(['spotify_link_party_id' => $party->id])->get(route('spotify.link.return'))->assertForbidden();

    expect(LinkedAccount::query()->count())->toBe(0);
});

it('refuses an external account already linked to another user', function () {
    $other = makeUser();
    LinkedAccount::factory()->for($other)->create(['social_provider_id' => spotifyProvider()->id, 'external_id' => 'spotify-user-1']);
    $host = makeUser();
    $party = makeHostedParty($host);
    mockSpotifyCallback();

    $this->actingAs($host)->withSession(['spotify_link_party_id' => $party->id])->get(route('spotify.link.return'))->assertRedirect(route('home'));

    expect(LinkedAccount::query()->where('user_id', $host->id)->exists())->toBeFalse();
});

it('dispatches a history append only when a history playlist is set', function () {
    Bus::fake();
    [$host] = hostWithAccount();

    app(AppendPlayToHistory::class)(makeHostedParty($host), 'track-1', $this->fake);
    Bus::assertNotDispatched(AppendToHistoryPlaylist::class);

    app(AppendPlayToHistory::class)(makeHostedParty($host, ['history_playlist_id' => 'playlist-1']), 'track-1', $this->fake);
    Bus::assertDispatched(AppendToHistoryPlaylist::class);
});

it('appends the played track to the history playlist through the provider', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host, ['history_playlist_id' => 'playlist-1']);

    app(AppendPlayToHistory::class)($party, 'track-2', $this->fake);

    expect($this->fake->appendedTo('playlist-1'))->toBe(['track-2']);
});

it('appends one Play to the history playlist once however often the job runs', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host, ['history_playlist_id' => 'playlist-1']);
    $play = Play::factory()->for($party)->create(['provider_track_id' => 'track-1']);

    foreach ([1, 2] as $_) {
        new AppendToHistoryPlaylist($party->id, 'track-1', 'fake', $play->id)->handle(app(PairingCatalogue::class), app(AuthorisesHost::class));
    }

    expect($this->fake->appendedTo('playlist-1'))->toBe(['track-1'])
        ->and($play->fresh()->history_appended_at)->not->toBeNull();
});

it('retries a temporary failure without throwing into playback', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host, ['history_playlist_id' => 'playlist-1']);
    $this->fake->rateLimitNext(42);

    $job = new class($party->id, 'track-1', 'fake') extends AppendToHistoryPlaylist
    {
        public ?int $releasedFor = null;

        public function attempts(): int
        {
            return 1;
        }

        public function release($delay = 0): void
        {
            $this->releasedFor = $delay;
        }
    };

    $job->handle(app(PairingCatalogue::class), app(AuthorisesHost::class));

    expect($this->fake->appendedTo('playlist-1'))->toBe([])
        ->and($job->releasedFor)->toBe(42);
});

it('logs a final failure without tokens and does not throw', function () {
    [$host, $account] = hostWithAccount();
    $party = makeHostedParty($host, ['history_playlist_id' => 'playlist-1']);
    $this->fake->failNextWith(new ProviderTemporaryFailure('down'));
    Log::spy();

    $job = new class($party->id, 'track-1', 'fake') extends AppendToHistoryPlaylist
    {
        public function attempts(): int
        {
            return 5;
        }
    };

    $job->handle(app(PairingCatalogue::class), app(AuthorisesHost::class));

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context), $account->access_token));
});

it('skips silently when the host has no linked account', function () {
    $host = makeUser();
    $party = makeHostedParty($host, ['history_playlist_id' => 'playlist-1']);

    app(AppendPlayToHistory::class)($party, 'track-1', $this->fake);

    expect($this->fake->appendedTo('playlist-1'))->toBe([]);
});

it('tops the queue up from the playlist chosen through the picker', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host, ['state' => PartyState::Live, 'music_provider' => 'fake']);
    $tracks = array_map(playbackTrack(...), range(1, 8));
    $fake = new FakeMusicProvider($tracks, [new PlaylistData('playlist-1', 'Fallback Mix')], ['playlist-1' => $tracks]);
    $this->app->instance(FakeMusicProvider::class, $fake);
    Sanctum::actingAs($host);

    $this->putJson(route('api.v1.parties.playlists.update', $party), ['fallback_playlist_id' => 'playlist-1', 'history_playlist_id' => null])->assertOk();

    expect(app(TopUpFallbackRequests::class)($party->fresh()))->toBe(5)
        ->and(TrackRequest::query()->where('party_id', $party->id)->whereNull('party_member_id')->count())->toBe(5);
});

function startTrackedRequest(Party $party): TrackRequest
{
    $request = TrackRequest::factory()->for($party)->create(['provider_track_id' => 'track-1', 'status' => RequestStatus::UpNext]);
    Party::flushEventListeners();

    return $request;
}

it('appends the started track to the history playlist when the queue advances', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host, ['history_playlist_id' => 'playlist-1']);
    startTrackedRequest($party);

    app(AdvanceQueue::class)($party, 'track-1');

    expect($this->fake->appendedTo('playlist-1'))->toBe(['track-1']);
});

it('appends nothing when no history playlist is set', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host);
    startTrackedRequest($party);

    app(AdvanceQueue::class)($party, 'track-1');

    expect($this->fake->appendedTo('playlist-1'))->toBe([]);
});

it('writes a party log entry and does not throw when the provider fails on advance', function () {
    [$host] = hostWithAccount();
    $party = makeHostedParty($host, ['history_playlist_id' => 'playlist-1']);
    startTrackedRequest($party);
    $this->fake->failNextWith(new ProviderUnavailableException('down'));

    $advance = app(AdvanceQueue::class)($party, 'track-1');

    expect($advance->playing)->not->toBeNull()
        ->and(PartyLogEntry::query()->where('party_id', $party->id)->where('action', 'playlist.history_append_failed')->exists())->toBeTrue();
});

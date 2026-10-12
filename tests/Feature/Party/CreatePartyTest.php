<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Music\Providers\Spotify\SpotifyScopes;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Testing\FakePlayer;
use App\Domain\Queue\SelectionMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function partyPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Friday LAN', 'music_provider' => 'fake', 'player_kind' => 'fake'], $overrides);
}

function registerIncompatiblePlayer(): void
{
    config(['musicparty.players.other' => ['label' => 'Other player', 'class' => 'test.other']]);
    app()->bind('test.other', fn (): FakePlayer => new FakePlayer(kind: 'other', compatibleProviders: ['elsewhere']));
}

it('creates a party through the web as a user with the create-party role', function (string $role) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->post(route('parties.store'), partyPayload())
        ->assertRedirect(route('parties.show', ['party' => Party::query()->firstOrFail()->code]));

    $party = Party::query()->firstOrFail();
    expect($party->code)->toMatch('/^[A-Z]{4}$/')
        ->and($party->state)->toBe(PartyState::Paused)
        ->and($party->user_id)->toBe($user->id)
        ->and($party->music_provider)->toBe('fake')
        ->and($party->player_kind)->toBe('fake')
        ->and($party->memberFor($user)->role)->toBe(PartyRole::Host);
})->with(['create-party', 'admin']);

it('creates a party through the API', function (string $role) {
    $user = User::factory()->withRole($role)->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/parties', partyPayload())->assertCreated();

    $party = Party::query()->firstOrFail();
    $response->assertExactJson(['data' => [
        'code' => $party->code,
        'name' => 'Friday LAN',
        'state' => 'paused',
        'music_provider' => 'fake',
        'player_kind' => 'fake',
        'fallback_playlist_id' => null,
        'hold_requests' => false,
        'explicit' => true,
        'min_song_length' => null,
        'max_song_length' => null,
        'no_repeat_interval' => null,
        'downvotes' => true,
        'downvotes_per_hour' => null,
        'selection_mode' => 'deterministic',
        'role' => 'host',
    ]]);
    expect($party->code)->toMatch('/^[A-Z]{4}$/')
        ->and(PartyMember::query()->where('party_id', $party->id)->where('role', 'host')->count())->toBe(1);
})->with(['create-party', 'admin']);

it('refuses party creation for a plain user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('parties.store'), partyPayload())->assertForbidden();
    Sanctum::actingAs($user);
    $this->postJson('/api/v1/parties', partyPayload())->assertForbidden();

    expect(Party::query()->count())->toBe(0);
});

it('requires authentication to create a party', function () {
    $this->post(route('parties.store'), partyPayload())->assertRedirect(route('login'));
    $this->postJson('/api/v1/parties', partyPayload())->assertUnauthorized();
});

it('rejects an incompatible player and provider pairing', function () {
    registerIncompatiblePlayer();
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->post(route('parties.store'), partyPayload(['player_kind' => 'other']))
        ->assertSessionHasErrors('player_kind');

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/parties', partyPayload(['player_kind' => 'other']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('player_kind');

    expect(Party::query()->count())->toBe(0);
});

it('validates the create payload', function (array $payload, string $field) {
    Sanctum::actingAs(User::factory()->withRole('create-party')->create());

    $this->postJson('/api/v1/parties', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing name' => [['music_provider' => 'fake', 'player_kind' => 'fake'], 'name'],
    'short name' => [['name' => 'a', 'music_provider' => 'fake', 'player_kind' => 'fake'], 'name'],
    'unknown provider' => [['name' => 'Party', 'music_provider' => 'nope', 'player_kind' => 'fake'], 'music_provider'],
    'unknown player' => [['name' => 'Party', 'music_provider' => 'fake', 'player_kind' => 'nope'], 'player_kind'],
]);

it('serves the create page with the pairing catalogue', function () {
    $this->withoutVite()->actingAs(User::factory()->withRole('create-party')->create())
        ->get(route('parties.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Party/Create')
            ->where('providers', [['id' => 'fake', 'label' => 'Fake (development)']])
            ->where('players', [
                ['kind' => 'fake', 'label' => 'Fake player', 'compatibleProviders' => ['fake'], 'requiresControlLink' => false],
                ['kind' => 'polling', 'label' => 'Polling player', 'compatibleProviders' => ['spotify'], 'requiresControlLink' => true],
                ['kind' => 'browser', 'label' => 'Browser player', 'compatibleProviders' => ['spotify'], 'requiresControlLink' => true],
                ['kind' => 'soloist', 'label' => 'Soloist player', 'compatibleProviders' => ['spotify'], 'requiresControlLink' => false],
            ]));
});

it('forbids the create page without the role', function () {
    $this->withoutVite()->actingAs(User::factory()->create())->get(route('parties.create'))->assertForbidden();
});

it('tells the home page whether the user can create a party', function (bool $allowed) {
    $user = $allowed ? User::factory()->withRole('create-party')->create() : User::factory()->create();

    $this->withoutVite()->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('canCreateParty', $allowed));
})->with([true, false]);

function registerControlLinkPlayer(): void
{
    config(['musicparty.players.linked' => ['label' => 'Linked player', 'class' => 'test.linked']]);
    app()->bind('test.linked', fn (): FakePlayer => new FakePlayer(kind: 'linked', requiresHostAccount: true));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function linkSpotifyControl(User $user, array $overrides = []): void
{
    $provider = SocialProvider::query()->where('code', 'spotify')->first()
        ?? SocialProvider::factory()->create(['code' => 'spotify']);

    LinkedAccount::factory()->create(array_merge([
        'user_id' => $user->id,
        'social_provider_id' => $provider->id,
        'scopes' => implode(' ', SpotifyScopes::control()),
    ], $overrides));
}

it('refuses a player that needs a control link when none is linked', function () {
    registerControlLinkPlayer();
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->post(route('parties.store'), partyPayload(['player_kind' => 'linked']))
        ->assertSessionHasErrors('player_kind');

    expect(Party::query()->count())->toBe(0);
});

it('refuses a player that needs a control link when the link needs relinking', function () {
    registerControlLinkPlayer();
    $user = User::factory()->withRole('create-party')->create();
    linkSpotifyControl($user, ['needs_relink' => true]);

    $this->actingAs($user)->post(route('parties.store'), partyPayload(['player_kind' => 'linked']))
        ->assertSessionHasErrors('player_kind');
});

it('creates a party with a control link present', function () {
    registerControlLinkPlayer();
    $user = User::factory()->withRole('create-party')->create();
    linkSpotifyControl($user);

    $this->actingAs($user)->post(route('parties.store'), partyPayload(['player_kind' => 'linked']))
        ->assertSessionHasNoErrors();

    expect(Party::query()->count())->toBe(1);
});

it('does not require a control link for a player that needs no host account', function () {
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->post(route('parties.store'), partyPayload())->assertSessionHasNoErrors();

    expect(Party::query()->count())->toBe(1);
});

it('creates a party with Spotify Search enabled and no Search link', function () {
    registerControlLinkPlayer();
    config(['musicparty.spotify_search.enabled' => true]);
    $user = User::factory()->withRole('create-party')->create();
    linkSpotifyControl($user);

    $this->actingAs($user)->post(route('parties.store'), partyPayload(['player_kind' => 'linked']))
        ->assertSessionHasNoErrors();

    expect(Party::query()->count())->toBe(1);
});

it('applies the submitted settings at creation', function () {
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->post(route('parties.store'), partyPayload([
        'downvotes' => false,
        'downvotes_per_hour' => 5,
        'selection_mode' => 'weighted',
        'allow_requests' => true,
        'max_requests' => 3,
        'hold_requests' => true,
    ]))->assertSessionHasNoErrors();

    $party = Party::query()->firstOrFail();
    expect($party->downvotes)->toBeFalse()
        ->and($party->downvotes_per_hour)->toBe(5)
        ->and($party->selection_mode)->toBe(SelectionMode::Weighted)
        ->and($party->allow_requests)->toBeTrue()
        ->and($party->max_requests)->toBe(3)
        ->and($party->hold_requests)->toBeTrue();
});

it('rejects invalid settings', function (array $overrides, string $field) {
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->post(route('parties.store'), partyPayload($overrides))->assertSessionHasErrors($field);
})->with([
    'selection mode' => [['selection_mode' => 'bogus'], 'selection_mode'],
    'max requests' => [['max_requests' => 0], 'max_requests'],
    'downvotes per hour' => [['downvotes_per_hour' => -1], 'downvotes_per_hour'],
]);

it('exposes control-link requirements on the create page', function () {
    registerControlLinkPlayer();
    $user = User::factory()->withRole('create-party')->create();

    $this->actingAs($user)->get(route('parties.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('control_link_usable', false)
            ->where('players.4.kind', 'linked')
            ->where('players.4.requiresControlLink', true));
});

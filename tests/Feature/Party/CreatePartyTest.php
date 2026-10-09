<?php

use App\Domain\Party\PartyRole;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Testing\FakePlayer;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

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
            ->where('players', [['kind' => 'fake', 'label' => 'Fake player', 'compatibleProviders' => ['fake']]]));
});

it('forbids the create page without the role', function () {
    $this->withoutVite()->actingAs(User::factory()->create())->get(route('parties.create'))->assertForbidden();
});

it('tells the home page whether the user can create a party', function (bool $allowed) {
    $user = $allowed ? User::factory()->withRole('create-party')->create() : User::factory()->create();

    $this->withoutVite()->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('canCreateParty', $allowed));
})->with([true, false]);

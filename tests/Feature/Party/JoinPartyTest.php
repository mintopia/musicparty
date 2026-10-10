<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('joins a party as a guest through the web', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('parties.join'), ['code' => 'ABCD'])
        ->assertRedirect(route('parties.show', ['party' => 'ABCD']));

    expect($party->memberFor($user)->role)->toBe(PartyRole::Guest);
});

it('accepts a lowercase code', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('parties.join'), ['code' => 'abcd'])
        ->assertRedirect(route('parties.show', ['party' => 'ABCD']));

    expect($party->memberFor($user))->not->toBeNull();
});

it('reports an unknown code without creating a membership', function () {
    $this->actingAs(User::factory()->create())->post(route('parties.join'), ['code' => 'ZZZZ'])
        ->assertSessionHasErrors(['code' => 'No party found with that code.']);

    expect(PartyMember::query()->count())->toBe(0);
});

it('validates the join code', function (string $code) {
    $this->actingAs(User::factory()->create())->post(route('parties.join'), ['code' => $code])
        ->assertSessionHasErrors('code');
})->with(['too short' => 'AB', 'too long' => 'ABCDE', 'digits' => '1234']);

it('joins a party through the API', function () {
    $party = Party::factory()->create(['code' => 'ABCD']);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/parties/abcd/join')
        ->assertOk()
        ->assertJsonPath('data.code', 'ABCD')
        ->assertJsonPath('data.role', 'guest');

    expect($party->memberFor($user))->not->toBeNull();
});

it('returns 404 when joining an unknown party through the API', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/parties/ZZZZ/join')->assertNotFound();
});

it('requires authentication to join through the API', function () {
    Party::factory()->create(['code' => 'ABCD']);

    $this->postJson('/api/v1/parties/ABCD/join')->assertUnauthorized();
});

it('keeps an existing elevated role when rejoining', function (string $state) {
    $party = Party::factory()->create();
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->{$state}()->create();

    $this->actingAs($user)->post(route('parties.join'), ['code' => $party->code])->assertRedirect();

    expect(PartyMember::query()->where('party_id', $party->id)->where('user_id', $user->id)->count())->toBe(1)
        ->and($party->memberFor($user)->role)->toBe($state === 'host' ? PartyRole::Host : PartyRole::Moderator);
})->with(['host', 'moderator']);

it('lets a user join an ended party in read-only mode', function () {
    $party = Party::factory()->ended()->create();
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($user)->post(route('parties.join'), ['code' => $party->code])->assertRedirect();
    expect($party->memberFor($user))->not->toBeNull();

    $this->withoutVite()->actingAs($user)->get(route('parties.show', ['party' => $party->code]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('readOnly', true));
});

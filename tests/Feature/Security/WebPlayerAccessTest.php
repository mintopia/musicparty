<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Role;
use App\Models\User;
use App\Services\Spotify\SpotifyGateway;
use App\Services\Spotify\SpotifyRelinkRequiredException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function partyWithMember(string $roleCode): array
{
    $host = User::factory()->create();
    $party = new Party;
    $party->code = 'ABCD';
    $party->name = 'Test Party';
    $party->user_id = $host->id;
    Party::withoutEvents(fn () => $party->save());

    $member = User::factory()->create();
    $role = PartyMemberRole::query()->forceCreate(['code' => $roleCode, 'name' => ucfirst($roleCode)]);
    $row = new PartyMember;
    $row->party_id = $party->id;
    $row->user_id = $member->id;
    $row->role_id = $role->id;
    $row->save();

    return [$party, $member];
}

function webPlayerAdmin(): User
{
    Route::getRoutes()->refreshNameLookups();
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));

    return $admin;
}

function webPlayerChannelAllows(User $user, Party $party): bool
{
    $callback = Broadcast::driver()->getChannels()->get('webplayer.{party}');

    return (bool) $callback($user, $party);
}

beforeEach(function () {
    $this->withoutVite();
    $this->mock(SpotifyGateway::class)->shouldReceive('accessToken')->andReturn('secret-token')->byDefault();
});

it('forbids a manager without the owner role from the page, token and channel', function () {
    [$party, $member] = partyWithMember('vip');

    $this->actingAs($member)->get(route('parties.player', $party))->assertForbidden();
    $this->actingAs($member)->getJson(route('parties.player.token', $party))->assertForbidden();
    expect(webPlayerChannelAllows($member, $party))->toBeFalse();
});

it('forbids a plain member from the page, token and channel', function () {
    [$party, $member] = partyWithMember('user');

    $this->actingAs($member)->get(route('parties.player', $party))->assertForbidden();
    $this->actingAs($member)->getJson(route('parties.player.token', $party))->assertForbidden();
    expect(webPlayerChannelAllows($member, $party))->toBeFalse();
});

it('redirects a guest away from the page and token', function () {
    [$party] = partyWithMember('owner');

    $this->get(route('parties.player', $party))->assertRedirect();
    $this->getJson(route('parties.player.token', $party))->assertUnauthorized();
});

it('renders the web player for an owner-role member without the token in the HTML', function () {
    [$party, $member] = partyWithMember('owner');

    $response = $this->actingAs($member)->get(route('parties.player', $party));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Parties/WebPlayer')
        ->where('party.code', $party->code)
        ->where('tokenUrl', route('parties.player.token', $party))
        ->missing('accessToken'));
    expect($response->getContent())->not->toContain('secret-token');
    expect(webPlayerChannelAllows($member, $party))->toBeTrue();
});

it('renders the web player for an instance admin', function () {
    [$party] = partyWithMember('user');
    $admin = webPlayerAdmin();

    $this->actingAs($admin)->get(route('parties.player', $party))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Parties/WebPlayer'));
    expect(webPlayerChannelAllows($admin, $party))->toBeTrue();
});

it('serves the token to the owner-role member and admin, uncached', function () {
    [$party, $member] = partyWithMember('owner');

    $this->actingAs($member)->getJson(route('parties.player.token', $party))
        ->assertOk()->assertExactJson(['accessToken' => 'secret-token'])
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs(webPlayerAdmin())->getJson(route('parties.player.token', $party))->assertOk();
});

it('reports a missing Spotify link or a needed re-link from the token endpoint', function () {
    [$party, $member] = partyWithMember('owner');
    $gateway = $this->mock(SpotifyGateway::class);
    $gateway->shouldReceive('accessToken')->once()->andReturn(null);
    $this->actingAs($member)->getJson(route('parties.player.token', $party))->assertNotFound();

    $gateway->shouldReceive('accessToken')->once()->andThrow(new SpotifyRelinkRequiredException);
    $this->actingAs($member)->getJson(route('parties.player.token', $party))->assertStatus(409);
});

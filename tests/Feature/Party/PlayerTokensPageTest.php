<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Support\Realtime\PlayerConnections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{Party, User}
 */
function tokensParty(string $role): array
{
    $user = User::factory()->create();
    $party = Party::factory()->create(['code' => 'ABCD', 'user_id' => $role === 'host' ? $user->id : User::factory()->create()->id]);
    $member = PartyMember::factory()->for($party)->for($user);
    ($role === 'guest' ? $member : $member->{$role}())->create();

    return [$party, $user];
}

it('lists tokens with label, last used and expiry for the host', function () {
    [$party, $host] = tokensParty('host');
    $party->createToken('Stage', ['player:connect'], now()->addDays(30));

    $this->withoutVite()->actingAs($host)->get(route('parties.player-tokens.index', ['party' => 'ABCD']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Party/PlayerTokens')
            ->where('party.code', 'ABCD')
            ->where('issuedToken', null)
            ->has('tokens', 1, fn (Assert $t) => $t
                ->where('name', 'Stage')->where('last_used_at', null)->has('expires_at')->has('id')));
});

it('refuses the page and every action to non-hosts', function (string $role) {
    [$party, $user] = tokensParty($role);
    $token = $party->createToken('Stage', ['player:connect'])->accessToken;

    $this->withoutVite()->actingAs($user)->get(route('parties.player-tokens.index', ['party' => 'ABCD']))->assertForbidden();
    $this->withoutVite()->actingAs($user)->post(route('parties.player-tokens.store', ['party' => 'ABCD']), ['name' => 'X'])->assertForbidden();
    $this->withoutVite()->actingAs($user)->delete(route('parties.player-tokens.destroy', ['party' => 'ABCD', 'token' => $token->id]))->assertForbidden();

    expect($party->tokens()->count())->toBe(1);
})->with(['moderator', 'guest']);

it('shows the issued token only in the issue response', function () {
    [$party, $host] = tokensParty('host');

    $this->withoutVite()->actingAs($host)->post(route('parties.player-tokens.store', ['party' => 'ABCD']), ['name' => 'Stage'])
        ->assertRedirect(route('parties.player-tokens.index', ['party' => 'ABCD']));

    $issued = $this->withoutVite()->actingAs($host)->get(route('parties.player-tokens.index', ['party' => 'ABCD']));
    $issued->assertInertia(fn (Assert $page) => $page
        ->where('issuedToken.name', 'Stage')
        ->has('issuedToken.token')
        ->has('tokens', 1, fn (Assert $t) => $t->missing('token')->etc()));
    $plain = $issued->viewData('page')['props']['issuedToken']['token'];

    expect($party->tokens()->sole()->name)->toBe('Stage');

    $reloaded = $this->withoutVite()->actingAs($host)->get(route('parties.player-tokens.index', ['party' => 'ABCD']));
    $reloaded->assertInertia(fn (Assert $page) => $page->where('issuedToken', null));
    expect($reloaded->getContent())->not->toContain($plain);
});

it('requires a label of at most 64 characters to issue', function (mixed $name) {
    [, $host] = tokensParty('host');

    $this->withoutVite()->actingAs($host)->post(route('parties.player-tokens.store', ['party' => 'ABCD']), ['name' => $name])
        ->assertSessionHasErrors('name');
})->with(['missing' => [null], 'too long' => [str_repeat('a', 65)]]);

it('revokes a token and disconnects the player using it', function () {
    Cache::flush();
    [$party, $host] = tokensParty('host');
    $token = $party->createToken('Stage', ['player:connect'])->accessToken;
    $connections = app(PlayerConnections::class);
    $connections->register('sock.1', 'ABCD', $token->getKey());
    expect($connections->hasValidConnection('ABCD'))->toBeTrue();

    $this->withoutVite()->actingAs($host)->delete(route('parties.player-tokens.destroy', ['party' => 'ABCD', 'token' => $token->id]))
        ->assertRedirect(route('parties.player-tokens.index', ['party' => 'ABCD']));

    expect($party->tokens()->count())->toBe(0)
        ->and($connections->hasValidConnection('ABCD'))->toBeFalse();
});

it('returns not found when revoking a token of another party', function () {
    [, $host] = tokensParty('host');
    $other = Party::factory()->create(['code' => 'WXYZ']);
    $token = $other->createToken('Other', ['player:connect'])->accessToken;

    $this->withoutVite()->actingAs($host)->delete(route('parties.player-tokens.destroy', ['party' => 'ABCD', 'token' => $token->id]))->assertNotFound();
});

it('adds Player Tokens to the host menu only', function () {
    [, $host] = tokensParty('host');

    $this->withoutVite()->actingAs($host)->get(route('parties.player-tokens.index', ['party' => 'ABCD']))
        ->assertInertia(fn (Assert $page) => $page->where('partyContext.menu', fn ($menu): bool => collect($menu)->contains('key', 'player-tokens')));
});

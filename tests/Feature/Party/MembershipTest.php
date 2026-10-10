<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
});

function seatMember(Party $party, string $role, bool $banned = false): PartyMember
{
    $member = PartyMember::factory()->for($party);
    $member = $role === 'guest' ? $member : $member->{$role}();

    return ($banned ? $member->banned() : $member)->create();
}

function operationUrl(string $operation, PartyMember $target, string $prefix = '/api/v1'): string
{
    $suffix = str_starts_with($operation, 'role:') ? 'role' : 'ban';

    return "{$prefix}/parties/ABCD/members/{$target->id}/{$suffix}";
}

function callOperation(PartyMember $actor, string $operation, PartyMember $target)
{
    Sanctum::actingAs($actor->user);

    return match (true) {
        str_starts_with($operation, 'role:') => test()->putJson(operationUrl($operation, $target), ['role' => substr($operation, 5)]),
        $operation === 'ban' => test()->putJson(operationUrl($operation, $target)),
        default => test()->deleteJson(operationUrl($operation, $target)),
    };
}

dataset('permission matrix', [
    'host bans moderator' => ['host', false, 'moderator', false, 'ban', 200],
    'host bans vip' => ['host', false, 'vip', false, 'ban', 200],
    'host bans guest' => ['host', false, 'guest', false, 'ban', 200],
    'host cannot ban host' => ['host', false, 'host', false, 'ban', 403],
    'moderator bans vip' => ['moderator', false, 'vip', false, 'ban', 200],
    'moderator bans guest' => ['moderator', false, 'guest', false, 'ban', 200],
    'moderator cannot ban moderator' => ['moderator', false, 'moderator', false, 'ban', 403],
    'moderator cannot ban host' => ['moderator', false, 'host', false, 'ban', 403],
    'vip cannot ban' => ['vip', false, 'guest', false, 'ban', 403],
    'guest cannot ban' => ['guest', false, 'guest', false, 'ban', 403],
    'banned host cannot ban' => ['host', true, 'guest', false, 'ban', 403],
    'banned moderator cannot ban' => ['moderator', true, 'guest', false, 'ban', 403],
    'host unbans guest' => ['host', false, 'guest', true, 'unban', 200],
    'host unbans moderator' => ['host', false, 'moderator', true, 'unban', 200],
    'moderator unbans vip' => ['moderator', false, 'vip', true, 'unban', 200],
    'moderator cannot unban moderator' => ['moderator', false, 'moderator', true, 'unban', 403],
    'vip cannot unban' => ['vip', false, 'guest', true, 'unban', 403],
    'guest cannot unban' => ['guest', false, 'guest', true, 'unban', 403],
    'banned moderator cannot unban' => ['moderator', true, 'guest', true, 'unban', 403],
    'host promotes guest to moderator' => ['host', false, 'guest', false, 'role:moderator', 200],
    'host promotes guest to vip' => ['host', false, 'guest', false, 'role:vip', 200],
    'host demotes moderator to guest' => ['host', false, 'moderator', false, 'role:guest', 200],
    'host demotes vip to guest' => ['host', false, 'vip', false, 'role:guest', 200],
    'host cannot make another host' => ['host', false, 'guest', false, 'role:host', 422],
    'host role cannot be changed' => ['host', false, 'host', false, 'role:guest', 422],
    'moderator cannot change roles' => ['moderator', false, 'guest', false, 'role:vip', 403],
    'vip cannot change roles' => ['vip', false, 'guest', false, 'role:vip', 403],
    'guest cannot change roles' => ['guest', false, 'guest', false, 'role:moderator', 403],
    'banned host cannot change roles' => ['host', true, 'guest', false, 'role:vip', 403],
    'invalid role is rejected' => ['host', false, 'guest', false, 'role:overlord', 422],
]);

it('enforces the permission matrix', function (string $actorRole, bool $actorBanned, string $targetRole, bool $targetBanned, string $operation, int $status) {
    $actor = seatMember($this->party, $actorRole, $actorBanned);
    $target = $actorRole === $targetRole && $targetRole === 'host' ? $actor : seatMember($this->party, $targetRole, $targetBanned);
    $before = $target->only(['role', 'banned']);

    callOperation($actor, $operation, $target)->assertStatus($status);

    $after = $target->fresh()->only(['role', 'banned']);
    if ($status === 200) {
        expect($after)->not->toEqual($before);
    } else {
        expect($after)->toEqual($before)->and(PartyLogEntry::query()->count())->toBe(0);
    }
})->with('permission matrix');

it('lets the host ban a moderator while retaining their role and restores abilities on unban', function () {
    $host = seatMember($this->party, 'host');
    $moderator = seatMember($this->party, 'moderator');

    callOperation($host, 'ban', $moderator)->assertOk()->assertJsonPath('data.banned', true)->assertJsonPath('data.role', 'moderator');
    callOperation($moderator, 'ban', seatMember($this->party, 'guest'))->assertForbidden();

    callOperation($host, 'unban', $moderator)->assertOk()->assertJsonPath('data.banned', false);
    callOperation($moderator, 'ban', seatMember($this->party, 'guest'))->assertOk();
});

it('refuses a moderator banning themselves with a validation error', function () {
    $moderator = seatMember($this->party, 'moderator');

    callOperation($moderator, 'ban', $moderator)->assertUnprocessable()->assertJsonValidationErrors('member');
});

it('is idempotent and logs nothing for no-op changes', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');
    $banned = seatMember($this->party, 'guest', true);

    callOperation($host, 'role:guest', $guest)->assertOk();
    callOperation($host, 'unban', $guest)->assertOk();
    callOperation($host, 'ban', $banned)->assertOk();

    expect(PartyLogEntry::query()->count())->toBe(0);
});

it('writes Party Log entries for role changes, bans and unbans', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');

    callOperation($host, 'role:vip', $guest)->assertOk();
    callOperation($host, 'ban', $guest)->assertOk();
    callOperation($host, 'unban', $guest)->assertOk();

    $entries = PartyLogEntry::query()->orderBy('id')->get();
    expect($entries->pluck('action')->all())->toBe(['member.role_changed', 'member.banned', 'member.unbanned'])
        ->and($entries->pluck('user_id')->unique()->all())->toBe([$host->user_id])
        ->and($entries->pluck('subject')->unique()->all())->toBe([$guest->user->nickname])
        ->and($entries[0]->details)->toMatchArray(['old' => 'guest', 'new' => 'vip', 'user_id' => $guest->user_id])
        ->and($entries[1]->details)->toMatchArray(['role' => 'vip']);
});

it('returns 404 for a member of another party', function () {
    $host = seatMember($this->party, 'host');
    $stranger = PartyMember::factory()->for(Party::factory()->create(['code' => 'WXYZ']))->create();

    callOperation($host, 'ban', $stranger)->assertNotFound();
    callOperation($host, 'role:vip', $stranger)->assertNotFound();
    callOperation($host, 'unban', $stranger)->assertNotFound();
});

it('refuses non-members and unauthenticated callers', function () {
    $guest = seatMember($this->party, 'guest');

    $this->putJson(operationUrl('ban', $guest))->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    $this->putJson(operationUrl('ban', $guest))->assertForbidden();
    $this->getJson('/api/v1/parties/ABCD/members')->assertForbidden();
});

it('lists members with moderation detail for moderators and a trimmed list for others', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');
    $banned = seatMember($this->party, 'guest', true);

    Sanctum::actingAs($host->user);
    $this->getJson('/api/v1/parties/ABCD/members')->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.role', 'host')
        ->assertJsonPath('data.0.is_you', true)
        ->assertJsonStructure(['data' => [['id', 'nickname', 'avatar', 'role', 'banned', 'is_you']]]);

    Sanctum::actingAs($guest->user);
    $this->getJson('/api/v1/parties/ABCD/members')->assertOk()->assertJsonCount(2, 'data');

    Sanctum::actingAs($banned->user);
    $this->getJson('/api/v1/parties/ABCD/members')->assertForbidden();
});

it('renders the Members page with the props contract', function () {
    $host = seatMember($this->party, 'host');
    $banned = seatMember($this->party, 'guest', true);

    $this->actingAs($host->user)->get('/parties/ABCD/members')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Party/Members')
        ->where('party', ['code' => 'ABCD', 'name' => $this->party->name])
        ->has('members', 2)
        ->where('members.0.role', 'host')
        ->where('members.0.isYou', true)
        ->where('members.1.banned', true)
        ->where('members.1.id', $banned->id)
        ->where('abilities', ['canChangeRoles' => true, 'canBan' => true]));
});

it('gives moderators and plain members the right abilities on the Members page', function (string $role, bool $roles, bool $ban) {
    $member = seatMember($this->party, $role);

    $this->actingAs($member->user)->get('/parties/ABCD/members')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('abilities', ['canChangeRoles' => $roles, 'canBan' => $ban]));
})->with([
    'moderator' => ['moderator', false, true],
    'vip' => ['vip', false, false],
    'guest' => ['guest', false, false],
]);

it('refuses the Members page to banned members and non-members', function () {
    $banned = seatMember($this->party, 'guest', true);

    $this->actingAs($banned->user)->get('/parties/ABCD/members')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/parties/ABCD/members')->assertForbidden();
});

it('redirects guests to log in for the Members page', function () {
    $this->get('/parties/ABCD/members')->assertRedirect();
});

it('changes roles and bans through the web routes with the same outcome as the API', function () {
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');

    $this->actingAs($host->user)->from('/parties/ABCD/members')
        ->put("/parties/ABCD/members/{$guest->id}/role", ['role' => 'moderator'])
        ->assertRedirect('/parties/ABCD/members')->assertSessionHas('successMessage');
    expect($guest->fresh()->role)->toBe(PartyRole::Moderator);

    $this->actingAs($host->user)->put("/parties/ABCD/members/{$guest->id}/ban")->assertRedirect()->assertSessionHas('successMessage');
    expect($guest->fresh()->banned)->toBeTrue();

    $this->actingAs($host->user)->delete("/parties/ABCD/members/{$guest->id}/ban")->assertRedirect()->assertSessionHas('successMessage');
    expect($guest->fresh()->banned)->toBeFalse()
        ->and(PartyLogEntry::query()->pluck('action')->all())->toBe(['member.role_changed', 'member.banned', 'member.unbanned']);
});

it('refuses web moderation attempts according to the hierarchy', function () {
    $moderator = seatMember($this->party, 'moderator');
    $other = seatMember($this->party, 'moderator');
    $host = seatMember($this->party, 'host');
    $guest = seatMember($this->party, 'guest');

    $this->actingAs($moderator->user)->put("/parties/ABCD/members/{$other->id}/ban")->assertForbidden();
    $this->actingAs($moderator->user)->put("/parties/ABCD/members/{$host->id}/ban")->assertForbidden();
    $this->actingAs($moderator->user)->put("/parties/ABCD/members/{$guest->id}/role", ['role' => 'vip'])->assertForbidden();
    $this->actingAs($host->user)->put("/parties/ABCD/members/{$guest->id}/role", ['role' => 'host'])->assertSessionHasErrors('member');
    $this->actingAs($host->user)->put("/parties/ABCD/members/{$guest->id}/role", ['role' => 'nope'])->assertSessionHasErrors('role');
    $this->actingAs($host->user)->put('/parties/ABCD/members/999999/ban')->assertNotFound();

    expect(PartyLogEntry::query()->count())->toBe(0);
});

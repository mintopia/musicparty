<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PartyMemberRoleSeeder;

function memberRole(string $code): PartyMemberRole
{
    test()->seed(PartyMemberRoleSeeder::class);

    return PartyMemberRole::whereCode($code)->firstOrFail();
}

function partyWithOwner(): array
{
    $owningUser = User::factory()->create();
    $party = Party::withoutEvents(fn () => Party::forceCreate([
        'code' => fake()->unique()->lexify('??????'),
        'name' => 'Test',
        'user_id' => $owningUser->id,
    ]));

    return [$party, $owningUser];
}

function joinParty(Party $party, User $user, string $roleCode): PartyMember
{
    $member = new PartyMember;
    $member->party()->associate($party);
    $member->user()->associate($user);
    $member->role()->associate(memberRole($roleCode));
    $member->save();

    return $member;
}

function manager(Party $party): User
{
    $user = User::factory()->create();
    joinParty($party, $user, 'owner');

    return $user;
}

function admin(): User
{
    $user = User::factory()->create();
    $role = Role::whereCode('admin')->first() ?? Role::forceCreate(['code' => 'admin', 'name' => 'Admin']);
    $user->roles()->attach($role);

    return $user;
}

function assignRole(User $actor, Party $party, PartyMember $target, ?string $role): Illuminate\Testing\TestResponse
{
    return test()->actingAs($actor)->put(route('parties.users.update', [$party->code, $target->id]), ['role' => $role]);
}

it('rejects unknown and empty roles without nulling the role', function (?string $role) {
    [$party] = partyWithOwner();
    $target = joinParty($party, User::factory()->create(), 'user');

    assignRole(manager($party), $party, $target, $role)->assertSessionHasErrors('role');

    expect($target->fresh()->role->code)->toBe('user');
})->with(['unknown' => ['nonsense'], 'empty' => [''], 'null' => [null]]);

it('forbids a manager from assigning owner', function () {
    [$party] = partyWithOwner();
    $target = joinParty($party, User::factory()->create(), 'user');

    assignRole(manager($party), $party, $target, 'owner')->assertForbidden();

    expect($target->fresh()->role->code)->toBe('user');
});

it('forbids a manager from demoting an owner', function () {
    [$party] = partyWithOwner();
    $otherOwner = joinParty($party, User::factory()->create(), 'owner');

    assignRole(manager($party), $party, $otherOwner, 'user')->assertForbidden();

    expect($otherOwner->fresh()->role->code)->toBe('owner');
});

it('lets the owning user assign and demote owner', function () {
    [$party, $owningUser] = partyWithOwner();
    $target = joinParty($party, User::factory()->create(), 'user');

    assignRole($owningUser, $party, $target, 'owner')->assertRedirect();
    expect($target->fresh()->role->code)->toBe('owner');

    assignRole($owningUser, $party, $target, 'user')->assertRedirect();
    expect($target->fresh()->role->code)->toBe('user');
});

it('lets an admin assign owner', function () {
    [$party] = partyWithOwner();
    $target = joinParty($party, User::factory()->create(), 'user');

    assignRole(admin(), $party, $target, 'owner')->assertRedirect();

    expect($target->fresh()->role->code)->toBe('owner');
});

it('lets a manager set vip, user and banned', function (string $role) {
    [$party] = partyWithOwner();
    $target = joinParty($party, User::factory()->create(), 'user');

    assignRole(manager($party), $party, $target, $role)->assertRedirect();

    expect($target->fresh()->role->code)->toBe($role);
})->with(['vip', 'user', 'banned']);

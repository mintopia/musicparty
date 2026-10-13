<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PartyMemberRoleSeeder;

function orderingPartyWithNicknames(array $nicknames): array
{
    test()->seed(PartyMemberRoleSeeder::class);
    $role = PartyMemberRole::whereCode('user')->firstOrFail();
    $owner = User::factory()->create();
    $owner->roles()->attach(Role::forceCreate(['code' => 'admin', 'name' => 'Admin']));
    $party = Party::withoutEvents(fn () => Party::forceCreate([
        'code' => fake()->unique()->lexify('??????'),
        'name' => 'Ordering',
        'user_id' => $owner->id,
    ]));

    foreach ($nicknames as $nickname) {
        PartyMember::factory()->for($party)->for(User::factory()->create(['nickname' => $nickname]))->create(['role_id' => $role->id]);
    }

    return [$party, $owner];
}

it('honours the requested direction when sorting members by nickname', function (string $direction, array $expected) {
    [$party, $owner] = orderingPartyWithNicknames(['bravo', 'alpha', 'charlie']);

    $members = $this->withoutVite()->actingAs($owner)
        ->get(route('parties.users.index', [$party->code, 'order' => 'nickname', 'order_direction' => $direction]))
        ->assertOk()
        ->viewData('members');

    expect($members->getCollection()->map(fn (PartyMember $member) => $member->user->nickname)->all())->toBe($expected)
        ->and($members->getCollection()->every(fn (PartyMember $member) => $member->party_id === $party->id))->toBeTrue();
})->with([
    'ascending' => ['asc', ['alpha', 'bravo', 'charlie']],
    'descending' => ['desc', ['charlie', 'bravo', 'alpha']],
]);

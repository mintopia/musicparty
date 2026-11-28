<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PartyMemberRoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->withoutVite()->actingAs($owner)
        ->get(route('parties.users.index', [$party->code, 'order' => 'nickname', 'order_direction' => $direction]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Members/Index')
            ->has('members.data', 3)
            ->where('members.data', fn ($rows) => collect($rows)->pluck('nickname')->all() === $expected));
})->with([
    'ascending' => ['asc', ['alpha', 'bravo', 'charlie']],
    'descending' => ['desc', ['charlie', 'bravo', 'alpha']],
]);

it('pages through members without repeating or skipping rows and keeps per_page out of links', function () {
    [$party, $owner] = orderingPartyWithNicknames(array_map(fn (int $i): string => "member{$i}", range(1, 25)));
    $seen = [];

    foreach ([1, 2] as $page) {
        $members = $this->withoutVite()->actingAs($owner)
            ->get(route('parties.users.index', [$party->code, 'order' => 'role', 'page' => $page]))
            ->assertOk()
            ->viewData('page')['props']['members'];

        expect($members['next_page_url'] ?? '')->not->toContain('per_page');
        array_push($seen, ...array_column($members['data'], 'id'));
    }

    expect($seen)->toHaveCount($party->members()->count())->and(array_unique($seen))->toHaveCount(count($seen));
});

it('rejects member sorts that are not implemented', function () {
    [$party, $owner] = orderingPartyWithNicknames(['alpha']);

    $this->withoutVite()->actingAs($owner)
        ->get(route('parties.users.index', [$party->code, 'order' => 'votes']))
        ->assertSessionHasErrors('order');
});

<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\PartyMemberRoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PartyMemberRoleSeeder::class);
    $this->owner = User::factory()->create();
    $this->party = Party::withoutEvents(fn () => Party::forceCreate([
        'code' => fake()->unique()->lexify('??????'),
        'name' => 'Members Party',
        'user_id' => $this->owner->id,
    ]));
    $this->target = PartyMember::factory()->for($this->party)->for(User::factory()->create(['nickname' => 'Target']))
        ->create(['role_id' => PartyMemberRole::whereCode('user')->firstOrFail()->id]);
    $this->plain = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($this->plain)
        ->create(['role_id' => PartyMemberRole::whereCode('user')->firstOrFail()->id]);
});

it('renders the filtered members index for a manager', function () {
    $this->actingAs($this->owner)
        ->get(route('parties.users.index', [$this->party->code, 'nickname' => 'Targ', 'role' => 'user', 'per_page' => 5]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Members/Index')
            ->where('party.canManage', true)
            ->where('filters.nickname', 'Targ')
            ->where('filters.role', 'user')
            ->where('filters.per_page', '5')
            ->where('members.data.0.nickname', 'Target')
            ->where('members.data.0.url', route('parties.users.show', [$this->party->code, $this->target->id]))
            ->has('members.data', 1)
            ->has('roles', 5)
            ->has('playerState'));
});

it('renders member votes filtered by type', function () {
    $upcoming = UpcomingSong::factory()->create(['party_id' => $this->party->id]);
    Vote::factory()->up()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => $this->target->user_id]);

    $this->actingAs($this->owner)
        ->get(route('parties.users.show', [$this->party->code, $this->target->id, 'type' => 'upvote']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Members/Show')
            ->where('member.nickname', 'Target')
            ->where('member.roleCode', 'user')
            ->has('types', 2)
            ->has('votes.data', 1)
            ->where('votes.data.0.value', 1));

    $this->actingAs($this->owner)
        ->get(route('parties.users.show', [$this->party->code, $this->target->id, 'type' => 'downvote']))
        ->assertInertia(fn (Assert $page) => $page->has('votes.data', 0));
});

it('renders the edit form and flags self-editing', function () {
    $this->actingAs($this->owner)
        ->get(route('parties.users.edit', [$this->party->code, $this->target->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Members/Edit')
            ->where('isSelf', false)
            ->where('party.canManage', true)
            ->has('roles', 5));

    $self = PartyMember::factory()->for($this->party)->for($this->owner)
        ->create(['role_id' => PartyMemberRole::whereCode('owner')->firstOrFail()->id]);

    $this->actingAs($this->owner)
        ->get(route('parties.users.edit', [$this->party->code, $self->id]))
        ->assertInertia(fn (Assert $page) => $page->where('isSelf', true)->where('member.roleCode', 'owner'));
});

it('forbids non-managers from every member page', function (string $route) {
    $params = $route === 'parties.users.index' ? [$this->party->code] : [$this->party->code, $this->target->id];

    $this->actingAs($this->plain)->get(route($route, $params))->assertForbidden();
})->with(['parties.users.index', 'parties.users.show', 'parties.users.edit']);

it('redirects to the member with a flash after update', function () {
    $this->actingAs($this->owner)
        ->patch(route('parties.users.update', [$this->party->code, $this->target->id]), ['role' => 'vip'])
        ->assertRedirect(route('parties.users.show', [$this->party->code, $this->target->id]))
        ->assertSessionHas('successMessage', 'The user has been updated');

    expect($this->target->fresh()->role->code)->toBe('vip');
});

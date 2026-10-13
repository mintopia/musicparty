<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the Shell page with guest shared props', function () {
    $this->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shell')
            ->where('auth.user', null)
            ->where('auth.parties', [])
            ->has('flash')
            ->has('reverb.appKey')
            ->has('instanceName'));
});

it('shares the signed-in user and party memberships', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create(['user_id' => $user->id]);
    PartyMember::factory()->create(['party_id' => $party->id, 'user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.nickname', $user->nickname)
            ->where('auth.user.impersonating', false)
            ->has('auth.user.avatar')
            ->has('auth.user.roles')
            ->where('auth.parties.0.code', $party->code)
            ->where('auth.parties.0.canManage', true));
});

it('reads the Reverb key from config', function () {
    config(['reverb.apps.apps.0.key' => 'party-key']);

    $this->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page->where('reverb.appKey', 'party-key'));
});

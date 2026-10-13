<?php

use App\Models\Role;
use App\Models\User;

it('shares the signed-in user with the sidebar layout', function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));

    $this->withoutVite()->actingAs($user)->get(route('parties.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Parties/Create', false)->where('auth.user.id', $user->id));
});

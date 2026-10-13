<?php

use App\Models\Role;
use App\Models\User;

function sidebarLink(string $name): string
{
    return 'href="'.route($name).'"';
}

it('shows signed-in users Profile and Logout and no Login link in the sidebar', function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));

    $this->actingAs($user)->get(route('parties.create'))
        ->assertOk()
        ->assertSee(sidebarLink('user.profile'), false)
        ->assertSee(sidebarLink('logout'), false)
        ->assertDontSee(sidebarLink('login'), false);
});

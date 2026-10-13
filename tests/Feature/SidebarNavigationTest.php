<?php

use App\Models\User;

function sidebarLink(string $name): string
{
    return 'href="'.route($name).'"';
}

it('shows signed-in users Profile and Logout and no Login link in the sidebar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('user.profile'))
        ->assertOk()
        ->assertSee(sidebarLink('user.profile'), false)
        ->assertSee(sidebarLink('logout'), false)
        ->assertDontSee(sidebarLink('login'), false);
});

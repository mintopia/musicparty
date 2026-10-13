<?php

use App\Models\Party;
use App\Models\User;

function sidebarLink(string $name): string
{
    return 'href="'.route($name).'"';
}

it('shows guests a Login link and no Profile or Logout on the YouTube page', function () {
    $party = Party::factory()->create();

    $this->get(route('parties.youtube', $party->code))
        ->assertOk()
        ->assertSee(sidebarLink('login'), false)
        ->assertDontSee(sidebarLink('user.profile'), false)
        ->assertDontSee(sidebarLink('logout'), false);
});

it('shows signed-in users Profile and Logout and no Login link in the sidebar', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();

    $this->actingAs($user)->get(route('parties.youtube', $party->code))
        ->assertOk()
        ->assertSee(sidebarLink('user.profile'), false)
        ->assertSee(sidebarLink('logout'), false)
        ->assertDontSee(sidebarLink('login'), false);
});

<?php

use App\Models\Party;
use App\Models\User;

it('shows guests a Login link and no Profile or Logout on the YouTube page', function () {
    $party = Party::factory()->create();

    $response = $this->get(route('parties.youtube', $party->code));

    $response->assertOk()
        ->assertSee('href="' . route('login') . '"', false)
        ->assertDontSee('href="' . route('user.profile') . '"', false)
        ->assertDontSee('href="' . route('logout') . '"', false);
});

it('shows signed-in users Profile and Logout and no Login link in the sidebar', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();

    $response = $this->actingAs($user)->get(route('parties.youtube', $party->code));

    $response->assertOk()
        ->assertSee('href="' . route('user.profile') . '"', false)
        ->assertSee('href="' . route('logout') . '"', false)
        ->assertDontSee('href="' . route('login') . '"', false);
});

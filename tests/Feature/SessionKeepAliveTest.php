<?php

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('touches the session and returns no content for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('session.keep-alive'))
        ->assertNoContent()
        ->assertSessionHas('keep_alive_at');
});

it('refreshes the keep-alive timestamp on each ping', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession(['keep_alive_at' => 1])
        ->get(route('session.keep-alive'))
        ->assertNoContent();

    expect(session('keep_alive_at'))->toBeGreaterThan(1);
});

it('rejects guests', function () {
    $this->getJson(route('session.keep-alive'))->assertUnauthorized();
});

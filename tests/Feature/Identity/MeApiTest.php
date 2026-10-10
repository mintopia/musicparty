<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('returns the current user', function () {
    $user = User::factory()->create(['nickname' => 'dj']);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'nickname' => 'dj',
            'avatar' => $user->avatarUrl(),
            'signup_complete' => true,
        ]]);
});

it('flags an incomplete signup', function () {
    Sanctum::actingAs(User::factory()->firstLogin()->create());

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.signup_complete', false);
});

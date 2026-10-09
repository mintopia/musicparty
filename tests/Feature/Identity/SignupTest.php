<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires login to see the signup step', function () {
    $this->get(route('login.signup'))->assertRedirect();
    $this->post(route('login.signup.store'), [])->assertRedirect();
});

it('shows the signup page props to a first-time user', function () {
    $user = User::factory()->firstLogin()->create(['nickname' => 'newbie']);

    $this->actingAs($user)->get(route('login.signup'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Signup')
            ->where('nickname', 'newbie')
            ->has('termsUrl')
            ->has('privacyUrl'));
});

it('skips signup for a user who already completed it', function () {
    $this->actingAs(User::factory()->create())->get(route('login.signup'))->assertRedirect(route('home'));
});

it('completes signup with a nickname and terms acceptance', function () {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)
        ->post(route('login.signup.store'), ['nickname' => '  DJ Fresh ', 'terms' => '1'])
        ->assertRedirect(route('home'));

    $user->refresh();

    expect($user->nickname)->toBe('DJ Fresh')
        ->and($user->first_login)->toBeFalse()
        ->and($user->terms_agreed_at)->not->toBeNull();
});

it('rejects invalid signup input', function (array $payload, string $field) {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)->post(route('login.signup.store'), $payload)->assertSessionHasErrors($field);

    expect($user->fresh()->first_login)->toBeTrue()
        ->and($user->fresh()->terms_agreed_at)->toBeNull();
})->with([
    'missing nickname' => [['terms' => '1'], 'nickname'],
    'short nickname' => [['nickname' => 'a', 'terms' => '1'], 'nickname'],
    'long nickname' => [['nickname' => str_repeat('a', 33), 'terms' => '1'], 'nickname'],
    'terms not accepted' => [['nickname' => 'valid'], 'terms'],
    'terms declined' => [['nickname' => 'valid', 'terms' => '0'], 'terms'],
]);

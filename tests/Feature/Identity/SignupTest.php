<?php

use App\Domain\Admin\Models\Role;
use App\Domain\Admin\Models\Setting;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Actions\JoinParty;
use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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

function configureTerms(): void
{
    Setting::query()->where('code', 'terms')->delete();
    Setting::query()->forceCreate(['code' => 'terms', 'name' => 'Terms', 'value' => 'https://example.com/terms']);
    Cache::flush();
}

it('rejects invalid signup input', function (array $payload, string $field) {
    configureTerms();
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

it('keeps the terms step off when no terms are configured', function () {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)->post(route('login.signup.store'), ['nickname' => 'NoTerms'])
        ->assertRedirect(route('home'));

    expect($user->fresh()->first_login)->toBeFalse()
        ->and($user->fresh()->terms_agreed_at)->not->toBeNull();
});

it('requires terms acceptance when a terms url is configured', function () {
    configureTerms();
    $user = User::factory()->firstLogin()->create(['terms_agreed_at' => null]);

    $this->actingAs($user)->post(route('login.signup.store'), ['nickname' => 'valid'])->assertSessionHasErrors('terms');
    expect($user->fresh()->first_login)->toBeTrue();

    $this->actingAs($user)->post(route('login.signup.store'), ['nickname' => 'valid', 'terms' => '1'])->assertRedirect(route('home'));
    expect($user->fresh()->terms_agreed_at)->not->toBeNull();
});

it('redirects an unsigned-up user away from party creation and joining', function (string $method, string $route) {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)->{$method}(route($route), ['code' => 'ABCD'])->assertRedirect(route('login.signup'));
})->with([
    'create' => ['get', 'parties.create'],
    'join' => ['post', 'parties.join'],
]);

it('refuses the API with signup_required until signup is complete', function () {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user, 'sanctum')->postJson(route('api.v1.parties.store'), [])
        ->assertForbidden()
        ->assertJsonPath('code', 'signup_required');
});

it('keeps signup, logout and the me endpoint reachable for an unsigned-up user', function () {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)->get(route('login.signup'))->assertOk();
    $this->actingAs($user)->get(route('logout'))->assertRedirect();
    $this->actingAs($user, 'sanctum')->getJson(route('api.v1.me'))->assertOk();
});

it('refuses party creation and joining in the domain for incomplete signups', function () {
    $user = User::factory()->firstLogin()->create();
    $user->roles()->attach(Role::query()->firstOrCreate(['code' => 'admin'], ['name' => 'Admin']));

    expect($user->can('create', Party::class))->toBeFalse();
    expect(fn () => app(JoinParty::class)($user, Party::factory()->create()))->toThrow(AccessDeniedHttpException::class);
});

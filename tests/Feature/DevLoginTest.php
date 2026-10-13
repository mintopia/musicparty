<?php

use App\Http\Controllers\DevLoginController;
use App\Models\User;

it('logs in as an admin by nickname when enabled in a local environment', function () {
    config(['app.dev_login' => true]);
    app('router')->get('dev/login/{nickname}', DevLoginController::class)->name('dev.login.test');

    $this->get('/dev/login/tester')->assertRedirect(route('home'));

    $user = User::query()->where('nickname', 'tester')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->roles()->where('code', 'admin')->exists())->toBeTrue();
});

it('is absent unless explicitly enabled', function () {
    config(['app.dev_login' => false]);

    expect(DevLoginController::enabled())->toBeFalse();
    $this->get('/dev/login/tester')->assertNotFound();
});

it('is disabled outside local and testing environments', function () {
    config(['app.dev_login' => true]);
    app()->detectEnvironment(fn () => 'production');

    expect(DevLoginController::enabled())->toBeFalse();
});

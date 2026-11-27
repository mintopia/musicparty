<?php

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

it('redirects members and admins from /parties to home', function (bool $admin) {
    $user = User::factory()->create(['first_login' => false]);
    if ($admin) {
        $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
    }

    $this->actingAs($user)->get('/parties')->assertRedirect('/');
})->with([[false], [true]]);

it('does not error for guests on /parties', function () {
    expect($this->get('/parties')->status())->toBeLessThan(500);
});

it('points no registered route at a missing controller method', function () {
    $missing = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getAction('uses'))
        ->filter(fn ($uses) => is_string($uses) && str_contains($uses, '@'))
        ->reject(fn ($uses) => method_exists(...explode('@', $uses)))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

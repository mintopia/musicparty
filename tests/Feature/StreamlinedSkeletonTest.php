<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Listeners\MessageLoggedListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

it('has removed the legacy kernels and exception handler', function (string $path) {
    expect(base_path($path))->not->toBeFile();
})->with([
    'app/Http/Kernel.php',
    'app/Console/Kernel.php',
    'app/Exceptions/Handler.php',
]);

it('registers the webhooks middleware group and custom aliases', function () {
    $router = app(Router::class);

    expect($router->getMiddlewareGroups())->toHaveKeys(['web', 'api', 'webhooks'])
        ->and($router->getMiddleware()['auth'])->toBe(Authenticate::class)
        ->and($router->getMiddleware()['guest'])->toBe(RedirectIfAuthenticated::class);
});

it('does not flash sensitive inputs when validation fails', function () {
    Route::middleware('web')->post('/skeleton-validation', function () {
        request()->validate(['name' => 'required']);
    });

    $this->from('/form')
        ->post('/skeleton-validation', ['password' => 'secret', 'password_confirmation' => 'secret', 'current_password' => 'secret', 'other' => 'kept'])
        ->assertRedirect('/form')
        ->assertSessionHasErrors('name')
        ->assertSessionHasInput('other', 'kept')
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation')
        ->assertSessionMissing('_old_input.current_password');
});

it('renders JSON errors for unknown api routes and HTML 404 for web routes', function () {
    $this->getJson('/api/does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
    $this->get('/does-not-exist')->assertNotFound();
});

it('resolves the framework exception handler', function () {
    expect(app(ExceptionHandler::class))->toBeInstanceOf(Handler::class);
});

it('registers each listener once', function () {
    $registrations = fn (string $event, string $listener): int => collect(app('events')->getRawListeners()[$event] ?? [])
        ->filter(fn ($registered) => $registered === $listener)
        ->count();

    expect($registrations(MessageLogged::class, MessageLoggedListener::class))->toBe(1)
        ->and($registrations(Registered::class, SendEmailVerificationNotification::class))->toBe(1);
});

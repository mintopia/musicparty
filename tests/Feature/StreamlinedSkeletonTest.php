<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Listeners\MessageLoggedListener;
use App\Services\Spotify\BackoffRequest;
use App\Services\Spotify\SpotifyBackoffException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use SpotifyWebAPI\SpotifyWebAPIException;

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

it('logs a rate-limited Spotify exception at critical when reported, once', function () {
    Log::spy();

    report(new SpotifyWebAPIException('rate limited', 429));

    Log::shouldHaveReceived('critical')->with('Spotify Rate Limit Hit')->once();
});

it('does not log critical for non-rate-limit Spotify errors or active backoff short-circuits', function () {
    Log::spy();

    report(new SpotifyWebAPIException('bad request', 400));
    report(new SpotifyBackoffException(5));

    Log::shouldNotHaveReceived('critical');
});

it('does not double-log a rate limit handled by the backoff wrapper', function () {
    Log::spy();
    $request = new class('spotify') extends BackoffRequest
    {
        protected function transport(string $method, string $url, string|array|object $parameters, array $headers): array
        {
            throw new SpotifyWebAPIException('rate limited', 429);
        }
    };

    try {
        $request->send('GET', 'https://example.test');
    } catch (SpotifyWebAPIException $e) {
        report($e);
    }

    Log::shouldHaveReceived('critical')->with('Spotify Rate Limit Hit')->once();
});

it('renders JSON for exceptions on api routes and keeps the 404 status', function () {
    $this->getJson('/api/does-not-exist')->assertNotFound()->assertJsonPath('message', fn ($m) => is_string($m));
});

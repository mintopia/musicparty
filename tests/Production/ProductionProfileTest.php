<?php

use App\Http\Middleware\TrustProxies;
use App\Services\Contracts\MusicProvider;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use App\Services\RealtimeConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

it('boots under the production profile so production-only branches execute', function () {
    expect(TestCase::usesProductionProfile())->toBeTrue()
        ->and(app()->isProduction())->toBeTrue()
        ->and(config('app.debug'))->toBeFalse()
        ->and(parse_url((string) config('app.url'), PHP_URL_HOST))->not->toBe('localhost')
        ->and(parse_url((string) config('app.url'), PHP_URL_SCHEME))->toBeIn(['http', 'https']);
});

it('forces generated URLs via TrustedRootUrlServiceProvider onto APP_URL whatever Host the request claims', function () {
    $root = rtrim((string) config('app.url'), '/');

    $this->get('http://evil.example/login', ['Host' => 'evil.example']);

    expect(route('login'))->toStartWith($root.'/')
        ->and(URL::to('/anything'))->toBe($root.'/anything')
        ->and(URL::asset('build/app.css'))->toStartWith($root.'/');
});

it('defaults the session cookie to Secure only when APP_URL is https', function () {
    $secure = parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https';

    expect(config('session.secure'))->toBe($secure);
});

afterEach(fn () => Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR));

it('trusts the reverse proxy for the client address and scheme', function () {
    $request = Request::create('http://internal/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.2']);
    $request->headers->add(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'music.example.test']);

    new TrustProxies()->handle($request, fn (Request $trusted) => response('ok'));

    expect($request->ip())->toBe('203.0.113.9')
        ->and($request->isSecure())->toBeTrue()
        ->and($request->getHost())->toBe('music.example.test');
});

it('serves the realtime config for the APP_URL host with no localhost', function () {
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);
    $https = parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https';

    $response = $this->withoutVite()->get(route('login'));

    $response->assertOk()->assertDontSee('localhost');
    expect($response->getContent())->toContain($host)
        ->and(app(RealtimeConfig::class)->forRequest(request())['forceTLS'])->toBe($https);
});

it('keeps the Fake Music Provider out of MusicProviderFactory in production', function () {
    expect(MusicProviderFactory::fakeAllowed())->toBeFalse()
        ->and(app()->bound(FakeMusicProvider::class))->toBeFalse();
});

it('does not register the ReviewSeed command in production', function () {
    expect(array_keys(Artisan::all()))->not->toContain('review:seed');
});

it('leaves the AppServiceProvider test-only provider bindings out in production', function () {
    expect(app()->bound(MusicProvider::class))->toBeFalse();
});

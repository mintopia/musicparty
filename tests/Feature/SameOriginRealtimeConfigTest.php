<?php

use App\Services\RealtimeConfig;
use Illuminate\Http\Request;

it('renders the same-origin realtime config into pusherConfig', function () {
    config(['reverb.apps.apps.0.key' => 'party-key']);
    $this->app->instance('request', Request::create('https://party.example.test:8443/login'));

    $html = (string) view('partials._pusher-config');

    expect($html)->toContain('"appKey":"party-key"')
        ->toContain('"host":"party.example.test"')
        ->toContain('"port":8443')
        ->toContain('"forceTLS":true')
        ->not->toContain('env(');
});

it('shares the identical config through Inertia and the Blade partial', function () {
    config(['reverb.apps.apps.0.key' => 'party-key']);
    $expected = ['appKey' => 'party-key', 'host' => 'party.example.test', 'port' => 8443, 'forceTLS' => true];

    expect(app(RealtimeConfig::class)->forRequest(Request::create('https://party.example.test:8443/login')))->toBe($expected);
    $this->withoutVite()->get('https://party.example.test:8443/login')->assertSee(json_encode($expected), false);
});

it('does not call env() in any Blade view', function () {
    $offenders = collect(glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE))
        ->filter(fn (string $file) => str_contains(file_get_contents($file), 'env('));

    expect($offenders->all())->toBeEmpty();
});

it('limits Reverb allowed origins to the APP_URL host', function () {
    expect(config('reverb.apps.apps.0.allowed_origins'))->toBe([parse_url((string) config('app.url'), PHP_URL_HOST)]);
});

it('renders the realtime config after config is cached', function () {
    $cachePath = sys_get_temp_dir().'/config-cache-'.uniqid().'.php';

    try {
        $output = [];
        exec(sprintf(
            'APP_CONFIG_CACHE=%s %s %s config:cache 2>&1',
            escapeshellarg($cachePath),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
        ), $output, $status);
        expect($status)->toBe(0, implode("\n", $output));
        $cached = require $cachePath;
    } finally {
        @unlink($cachePath);
    }

    config(['reverb' => $cached['reverb']]);

    $this->withoutVite()->get('/login')
        ->assertOk()
        ->assertSee('window.pusherConfig = {"appKey":'.json_encode($cached['reverb']['apps']['apps'][0]['key']), false)
        ->assertDontSee('VITE_REVERB', false);
});

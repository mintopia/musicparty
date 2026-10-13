<?php

use App\Services\RealtimeConfig;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

it('shares the same-origin realtime config through Inertia', function () {
    config(['reverb.apps.apps.0.key' => 'party-key']);
    $expected = ['appKey' => 'party-key', 'host' => 'party.example.test', 'port' => 8443, 'forceTLS' => true];

    expect(app(RealtimeConfig::class)->forRequest(Request::create('https://party.example.test:8443/login')))->toBe($expected);
    $this->withoutVite()->get('https://party.example.test:8443/login')
        ->assertInertia(fn (Assert $page) => $page->where('reverb', $expected))
        ->assertDontSee('window.pusherConfig', false);
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
        ->assertInertia(fn (Assert $page) => $page->where('reverb.appKey', $cached['reverb']['apps']['apps'][0]['key']))
        ->assertDontSee('VITE_REVERB', false);
});

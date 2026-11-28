<?php

use App\Services\RealtimeConfig;
use Inertia\Testing\AssertableInertia as Assert;

it('shares the same-origin realtime config through Inertia', function () {
    config(['reverb.apps.apps.0.key' => 'party-key']);
    config(['app.url' => 'https://party.example.test:8443']);
    $expected = ['appKey' => 'party-key', 'host' => 'party.example.test', 'port' => 8443, 'forceTLS' => true];

    expect(app(RealtimeConfig::class)->forApp())->toBe($expected);
    $this->withoutVite()->get('https://alias.example.test/login')
        ->assertInertia(fn (Assert $page) => $page->where('reverb', $expected))
        ->assertDontSee('window.pusherConfig', false);
});

it('derives default ports from the app url scheme', function (string $url, int $port, bool $tls) {
    config(['app.url' => $url]);

    expect(app(RealtimeConfig::class)->forApp())->toMatchArray(['port' => $port, 'forceTLS' => $tls]);
})->with([['http://a.test', 80, false], ['https://a.test', 443, true]]);

it('does not call env() in any Blade view', function () {
    $offenders = collect(glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE))
        ->filter(fn (string $file) => str_contains(file_get_contents($file), 'env('));

    expect($offenders->all())->toBeEmpty();
});

it('limits Reverb allowed origins to the APP_URL host', function () {
    expect(config('reverb.apps.apps.0.allowed_origins'))->toBe([parse_url((string) config('app.url'), PHP_URL_HOST)]);
});

it('resolves Reverb allowed origins from REVERB_ALLOWED_ORIGINS', function (?string $value, ?array $expected) {
    $previous = getenv('REVERB_ALLOWED_ORIGINS');

    try {
        $value === null ? putenv('REVERB_ALLOWED_ORIGINS') : putenv("REVERB_ALLOWED_ORIGINS={$value}");
        $config = require config_path('reverb.php');

        expect($config['apps']['apps'][0]['allowed_origins'])
            ->toBe($expected ?? [parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)]);
    } finally {
        $previous === false ? putenv('REVERB_ALLOWED_ORIGINS') : putenv("REVERB_ALLOWED_ORIGINS={$previous}");
    }
})->with([
    'unset uses APP_URL host' => [null, null],
    'empty uses APP_URL host' => ['', null],
    'blank entries use APP_URL host' => [' , ,', null],
    'wildcard' => ['*', ['*']],
    'trimmed list' => ['a.example, b.example', ['a.example', 'b.example']],
]);

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

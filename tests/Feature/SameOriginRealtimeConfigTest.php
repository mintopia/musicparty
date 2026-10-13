<?php

it('renders only the Reverb app key into pusherConfig', function () {
    config(['reverb.apps.apps.0.key' => 'party-key']);

    $html = (string) view('partials._pusher-config');

    expect($html)->toContain('appKey: "party-key"')
        ->not->toContain('host')
        ->not->toContain('port')
        ->not->toContain('scheme');
});

it('does not call env() in any Blade view', function () {
    $offenders = collect(glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE))
        ->filter(fn (string $file) => str_contains(file_get_contents($file), 'env('));

    expect($offenders->all())->toBeEmpty();
});

it('limits Reverb allowed origins to the APP_URL host', function () {
    expect(config('reverb.apps.apps.0.allowed_origins'))->toBe([parse_url((string) config('app.url'), PHP_URL_HOST)]);
});

it('renders a page with only the app key after config is cached', function () {
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
        ->assertSee('window.pusherConfig = { appKey: '.json_encode($cached['reverb']['apps']['apps'][0]['key']).' };', false)
        ->assertDontSee('VITE_REVERB', false);
});

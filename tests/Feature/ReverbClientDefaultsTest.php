<?php

it('defaults the public settings from APP_URL when REVERB_PUBLIC_* are unset', function (string $appUrl, array $expected): void {
    foreach (['REVERB_PUBLIC_HOST', 'REVERB_PUBLIC_PORT', 'REVERB_PUBLIC_SCHEME'] as $name) {
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }
    putenv("APP_URL=$appUrl");
    $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $appUrl;

    $config = (require config_path('broadcasting.php'))['connections']['reverb']['client'];

    expect([$config['host'], (int) $config['port'], $config['scheme']])->toBe($expected);
})->with([
    'https' => ['https://music.example.com', ['music.example.com', 443, 'https']],
    'http with port' => ['http://localhost:8000', ['localhost', 8000, 'http']],
    'http' => ['http://lan.party', ['lan.party', 80, 'http']],
]);

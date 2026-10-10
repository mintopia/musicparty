<?php

declare(strict_types=1);

/**
 * @param  list<string>  $unset
 * @return array<string, mixed>
 */
function loadConfigWithout(string $file, array $unset): array
{
    $saved = [];
    foreach ($unset as $name) {
        $saved[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }

    try {
        return require config_path($file);
    } finally {
        foreach ($saved as $name => [$env, $envArray, $server]) {
            if ($env !== false) {
                putenv("$name=$env");
            }
            if ($envArray !== null) {
                $_ENV[$name] = $envArray;
            }
            if ($server !== null) {
                $_SERVER[$name] = $server;
            }
        }
    }
}

it('defaults the broadcaster to reverb', function (): void {
    expect(loadConfigWithout('broadcasting.php', ['BROADCAST_DRIVER'])['default'])->toBe('reverb');
});

it('defaults the queue to redis', function (): void {
    expect(loadConfigWithout('queue.php', ['QUEUE_CONNECTION'])['default'])->toBe('redis');
});

it('defaults the cache store to redis', function (): void {
    expect(loadConfigWithout('cache.php', ['CACHE_STORE'])['default'])->toBe('redis');
});

it('defaults the session driver to redis', function (): void {
    expect(loadConfigWithout('session.php', ['SESSION_DRIVER'])['driver'])->toBe('redis');
});

it('publishes to the compose reverb service when REVERB_HOST, PORT and SCHEME are unset', function (): void {
    $config = loadConfigWithout('broadcasting.php', ['REVERB_HOST', 'REVERB_PORT', 'REVERB_SCHEME']);

    expect($config['connections']['reverb']['options'])
        ->toMatchArray(['host' => 'reverb', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false]);
});

it('has no pusher or ably connections', function (): void {
    expect(loadConfigWithout('broadcasting.php', [])['connections'])->not->toHaveKeys(['pusher', 'ably']);
});

it('keeps only the required settings in .env.example', function (): void {
    expect(file_get_contents(base_path('.env.example')))
        ->not->toContain('PUSHER')
        ->not->toContain('ABLY')
        ->not->toContain('BROADCAST_DRIVER')
        ->not->toContain('QUEUE_CONNECTION')
        ->not->toContain('CACHE_STORE')
        ->not->toContain('CACHE_DRIVER')
        ->not->toContain('SESSION_DRIVER')
        ->toContain('REVERB_APP_ID=')
        ->toContain('SPOTIFY_CLIENT_ID=');
});

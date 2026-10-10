<?php

declare(strict_types=1);

use Tests\Support\DeployConfig;

dataset('compose files', ['docker-compose.yaml', 'example/docker-compose.yml']);

it('starts Reverb on its configured server port without debug', function () {
    $argv = DeployConfig::compose('docker-compose.yaml')->entrypoint('reverb');

    expect($argv)->toContain('reverb:start')->toContain('--host=0.0.0.0')
        ->and(array_filter($argv, fn (string $arg): bool => str_starts_with($arg, '--debug') || str_starts_with($arg, '--port')))->toBeEmpty();
});

it('loads the dev Reverb service environment from .env', function () {
    expect(DeployConfig::compose('docker-compose.yaml')->envFiles('reverb'))->toContain('.env');
});

it('has no VITE Reverb variables in the env files', function (string $file) {
    $vite = array_filter(array_keys(DeployConfig::env($file)), fn (string $key): bool => str_starts_with($key, 'VITE_REVERB'));

    expect($vite)->toBeEmpty();
})->with(['example/.env.example', 'docker/production/production.env', '.env.example']);

it('raises the open file limit for the Reverb service', function (string $file) {
    $nofile = DeployConfig::compose($file)->service('reverb')['ulimits']['nofile'] ?? [];

    expect($nofile)->toBe(['soft' => 65535, 'hard' => 65535]);
})->with('compose files');

it('installs the uv extension in the production and develop images', function (string $dockerfile) {
    $runs = implode("\n", array_column(
        array_filter(DeployConfig::dockerfile($dockerfile), fn (array $entry): bool => $entry['instruction'] === 'RUN'),
        'arguments',
    ));

    expect($runs)->toMatch('/install-php-extensions\b[^&]*\buv\b/');
})->with(['docker/production/Dockerfile', 'docker/develop/Dockerfile']);

<?php

declare(strict_types=1);

use Tests\Support\DeployConfig;

dataset('compose files', [
    'dev' => ['docker-compose.yaml'],
    'example' => ['example/docker-compose.yml'],
]);

it('starts Reverb on its configured server port without debug', function (string $file) {
    $argv = DeployConfig::compose($file)->entrypoint('reverb');

    expect($argv)->toContain('reverb:start')->toContain('--host=0.0.0.0')
        ->and(array_filter($argv, fn (string $arg): bool => str_starts_with($arg, '--debug') || str_starts_with($arg, '--port')))->toBeEmpty();
})->with('compose files');

it('loads the dev Reverb service environment from .env', function () {
    expect(DeployConfig::compose('docker-compose.yaml')->envFiles('reverb'))->toContain('.env');
});

it('has no VITE Reverb variables in the env files', function (string $file) {
    $vite = array_filter(array_keys(DeployConfig::env($file)), fn (string $key): bool => str_starts_with($key, 'VITE_REVERB'));

    expect($vite)->toBeEmpty();
})->with(['example/.env.example', 'docker/production/production.env', '.env.example']);

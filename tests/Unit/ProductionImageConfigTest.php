<?php

declare(strict_types=1);

use Tests\Support\DeployConfig;

function dockerfileInstructions(string $path, string $instruction): array
{
    return array_values(array_filter(
        DeployConfig::dockerfile($path),
        fn (array $entry): bool => $entry['instruction'] === $instruction,
    ));
}

it('copies lock files as the first source copy of the stage that installs dependencies', function (string $lock) {
    $stageCopies = [];

    foreach (dockerfileInstructions('docker/production/Dockerfile', 'COPY') as $entry) {
        if (! str_contains($entry['arguments'], '--from=')) {
            $stageCopies[$entry['stage']][] = $entry['arguments'];
        }
    }

    $stage = array_values(array_filter($stageCopies, fn (array $copies): bool => str_contains($copies[0], $lock)));

    expect($stage)->toHaveCount(1);
})->with(['composer.lock', 'package-lock.json']);

it('installs dependencies before the source is copied', function () {
    $instructions = DeployConfig::dockerfile('docker/production/Dockerfile');
    $position = fn (callable $matches): int => (int) array_key_first(array_filter($instructions, $matches));

    expect($position(fn (array $e): bool => $e['instruction'] === 'RUN' && str_contains($e['arguments'], 'composer install')))
        ->toBeLessThan($position(fn (array $e): bool => $e['instruction'] === 'COPY' && str_starts_with($e['arguments'], '. ')))
        ->and($position(fn (array $e): bool => $e['instruction'] === 'RUN' && str_contains($e['arguments'], 'npm ci')))
        ->toBeLessThan($position(fn (array $e): bool => $e['instruction'] === 'COPY' && str_contains($e['arguments'], 'resources')));
});

it('uses npm ci and never ignores platform requirements', function () {
    $runs = implode("\n", array_column(dockerfileInstructions('docker/production/Dockerfile', 'RUN'), 'arguments'));

    expect($runs)->toContain('npm ci')->not->toContain('npm install')->not->toContain('--ignore-platform-reqs');
});

it('exposes no port below 1024', function (string $dockerfile) {
    $ports = [];

    foreach (dockerfileInstructions($dockerfile, 'EXPOSE') as $entry) {
        $ports = [...$ports, ...preg_split('/\s+/', $entry['arguments'])];
    }

    expect($ports)->toBeArray()
        ->and(array_map(intval(...), $ports))->each->toBeGreaterThanOrEqual(1024);
})->with(['docker/production/Dockerfile', 'docker/develop/Dockerfile']);

it('serves the web role on 8080', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/docker/production/entrypoint.sh'))->toContain('--port=8080')
        ->and(array_column(dockerfileInstructions('docker/production/Dockerfile', 'EXPOSE'), 'arguments'))->toContain('8080');
});

it('runs every role through the single entrypoint with web as default', function () {
    $production = dockerfileInstructions('docker/production/Dockerfile', 'ENTRYPOINT');

    expect($production)->toHaveCount(1)
        ->and($production[0]['arguments'])->toContain('/entrypoint.sh')
        ->and(dockerfileInstructions('docker/production/Dockerfile', 'CMD')[0]['arguments'])->toContain('web');
});

it('sets up the user, drops privileges and caches before running a role', function () {
    $script = file_get_contents(dirname(__DIR__, 2).'/docker/production/entrypoint.sh');

    expect($script)
        ->toContain('PUID:-1000')->toContain('PGID:-1000')
        ->toContain('chown')->toContain('storage')->toContain('bootstrap/cache')
        ->toContain('artisan optimize')
        ->toContain('exec su-exec');

    foreach (['web', 'horizon', 'scheduler', 'reverb', 'migrate'] as $role) {
        expect($script)->toContain("{$role})");
    }

    expect($script)->toContain('optimize\' failed')
        ->and(strpos($script, 'artisan optimize'))->toBeLessThan(strpos($script, 'exec su-exec'))
        ->and(strpos($script, 'chown'))->toBeLessThan(strpos($script, 'artisan optimize'));
});

it('sets the agreed OpCache values for production', function () {
    $ini = DeployConfig::ini('docker/php/opcache.ini');

    expect($ini)->toMatchArray([
        'opcache.enable' => '1',
        'opcache.enable_cli' => '1',
        'opcache.validate_timestamps' => '0',
        'opcache.memory_consumption' => '256',
        'opcache.interned_strings_buffer' => '32',
        'opcache.max_accelerated_files' => '20000',
        'opcache.jit' => 'disable',
        'opcache.jit_buffer_size' => '0',
    ]);
});

it('keeps timestamp validation on in the dev image', function () {
    $ini = DeployConfig::ini('docker/php/opcache.dev.ini');
    $copies = implode("\n", array_column(dockerfileInstructions('docker/develop/Dockerfile', 'COPY'), 'arguments'));

    expect($ini['opcache.validate_timestamps'])->toBe('1')->and($ini['opcache.jit'])->toBe('disable')
        ->and($copies)->toContain('docker/php/opcache.dev.ini');
});

it('installs the production ini in the production image', function () {
    $copies = implode("\n", array_column(dockerfileInstructions('docker/production/Dockerfile', 'COPY'), 'arguments'));

    expect($copies)->toContain('docker/php/opcache.ini');
});

it('pins composer 2 in the dev image', function () {
    $copies = implode("\n", array_column(dockerfileInstructions('docker/develop/Dockerfile', 'COPY'), 'arguments'));

    expect($copies)->toContain('composer:2')->not->toContain('composer:latest');
});

<?php

declare(strict_types=1);

use Tests\Support\DeployConfig;

const EXAMPLE_COMPOSE = 'example/docker-compose.yml';

dataset('app services', ['web', 'horizon', 'scheduler', 'reverb', 'migrate', 'artisan']);

it('runs every app service by role command with no entrypoint override', function (string $service) {
    $compose = DeployConfig::compose(EXAMPLE_COMPOSE);

    expect($compose->service($service))->not->toHaveKey('entrypoint')
        ->and($compose->command($service))->not->toBeEmpty();
})->with('app services');

it('maps each long-running service to its role', function (string $service, string $role) {
    expect(DeployConfig::compose(EXAMPLE_COMPOSE)->command($service))->toBe([$role]);
})->with([
    'web' => ['web', 'web'],
    'horizon' => ['horizon', 'horizon'],
    'scheduler' => ['scheduler', 'scheduler'],
    'reverb' => ['reverb', 'reverb'],
    'migrate' => ['migrate', 'migrate'],
]);

it('defines healthchecks for MariaDB, Redis and web', function () {
    $compose = DeployConfig::compose(EXAMPLE_COMPOSE);

    expect($compose->healthcheck('database')['test'])->toBe(['CMD', 'healthcheck.sh', '--connect', '--innodb_initialized'])
        ->and($compose->healthcheck('redis')['test'])->toBe(['CMD', 'redis-cli', 'ping'])
        ->and(implode(' ', $compose->healthcheck('web')['test']))->toContain('http://localhost:8080/api/v1/ping');
});

it('runs migrate once and never restarts it', function () {
    expect(DeployConfig::compose(EXAMPLE_COMPOSE)->service('migrate')['restart'])->toBe('no');
});

it('holds web, horizon and the scheduler until migrate completes', function (string $service) {
    $dependsOn = DeployConfig::compose(EXAMPLE_COMPOSE)->service($service)['depends_on'];

    expect($dependsOn['migrate']['condition'])->toBe('service_completed_successfully');
})->with(['web', 'horizon', 'scheduler']);

it('makes every app service wait for healthy MariaDB and Redis', function (string $service) {
    $dependsOn = DeployConfig::compose(EXAMPLE_COMPOSE)->service($service)['depends_on'];

    expect($dependsOn['database']['condition'])->toBe('service_healthy')
        ->and($dependsOn['redis']['condition'])->toBe('service_healthy');
})->with('app services');

it('gives containers a grace period longer than Horizon supervisors need', function (string $service, string $grace) {
    expect(DeployConfig::compose(EXAMPLE_COMPOSE)->service($service)['stop_grace_period'])->toBe($grace);
})->with([
    'horizon' => ['horizon', '75s'],
    'web' => ['web', '30s'],
    'reverb' => ['reverb', '30s'],
]);

it('persists Redis with AOF on a bind mount', function () {
    $compose = DeployConfig::compose(EXAMPLE_COMPOSE);

    expect($compose->command('redis'))->toContain('--appendonly', 'yes')
        ->and($compose->volumes('redis'))->toBe(['./redis:/data']);
});

it('mounts app storage instead of public and uses bind mounts only', function (string $service) {
    $compose = DeployConfig::compose(EXAMPLE_COMPOSE);
    $volumes = $compose->volumes($service);

    expect($volumes)->toContain('./storage/app:/app/storage/app')
        ->and(implode(' ', $volumes))->not->toContain('/app/storage/app/public')
        ->and($compose->toArray())->not->toHaveKey('volumes');

    foreach ($volumes as $volume) {
        expect($volume)->toStartWith('./');
    }
})->with('app services');

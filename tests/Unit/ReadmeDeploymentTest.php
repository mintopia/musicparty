<?php

declare(strict_types=1);

use Tests\Support\DeployConfig;

function readme(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/README.md');
}

dataset('deployment topics', [
    'requirements' => '### Requirements',
    'first deploy' => '### First deploy',
    'ports' => '### Ports',
    'reverse proxy' => '### Reverse proxy',
    'trusted proxies' => '### Trusted proxies',
    'resource limits' => '### Resource limits',
    'backup and restore' => '### Backup and restore',
    'upgrading' => '### Upgrading',
]);

it('documents each deployment topic under its own heading', function (string $heading) {
    expect(readme())->toContain("\n{$heading}\n");
})->with('deployment topics');

it('covers the required operator terms', function (string $term) {
    expect(readme())->toContain($term);
})->with([
    'PUID', 'PGID', 'docker compose up -d --wait', 'migrate --force --isolated', '/app', '/apps',
    'TRUSTED_PROXIES', 'OTEL_EXPORTER_OTLP_ENDPOINT', 'PROMETHEUS_TOKEN', 'mem_limit', 'cpus', 'pids_limit',
    'mariadb-dump', 'From v2', 'From an earlier v3 build', 'Integration Token',
]);

it('sets resource limits for every shipped Compose service', function () {
    $services = array_keys(DeployConfig::compose('example/docker-compose.yml')->toArray()['services']);

    foreach (array_diff($services, ['artisan']) as $service) {
        expect(readme())->toMatch('/^  '.preg_quote((string) $service, '/').':\R    mem_limit:/m');
    }
});

it('documents the ports the example Compose file actually uses', function () {
    $healthcheck = implode(' ', DeployConfig::compose('example/docker-compose.yml')->healthcheck('web')['test']);

    expect($healthcheck)->toContain('localhost:8080')
        ->and(DeployConfig::env('docker/production/production.env')['REVERB_SERVER_PORT'])->toBe('8080')
        ->and(readme())->toContain('reverb:8080')->toContain('web:8080')->not->toContain('listens on port 80');
});

it('creates the bind-mount directories the example Compose file mounts', function () {
    expect(readme())->toContain('mkdir -p database redis logs storage/app');
});

it('keeps the Caddy example forwarding both Reverb paths', function () {
    expect(readme())->toContain('@reverb path /app /app/* /apps /apps/*');
});

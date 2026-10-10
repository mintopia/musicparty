<?php

declare(strict_types=1);

use Tests\Support\DeployConfig;

const DEV_COMPOSE = 'docker-compose.yaml';
const COMPOSE_FILES = [DEV_COMPOSE, 'example/docker-compose.yml'];

/**
 * @return list<string>
 */
function imageReferences(): array
{
    $references = [];

    foreach (COMPOSE_FILES as $file) {
        foreach (DeployConfig::compose($file)->services() as $service) {
            if (isset($service['image'])) {
                $references[] = (string) $service['image'];
            }
        }
    }

    foreach (['docker/develop/Dockerfile', 'docker/production/Dockerfile'] as $file) {
        foreach (DeployConfig::dockerfile($file) as $entry) {
            if ($entry['instruction'] === 'FROM') {
                $references[] = explode(' ', $entry['arguments'])[0];
            }
        }
    }

    $workflow = DeployConfig::workflow('.github/workflows/ci.yml')->toArray();

    foreach ($workflow['jobs'] ?? [] as $job) {
        foreach ($job['services'] ?? [] as $service) {
            $references[] = (string) $service['image'];
        }
    }

    return $references;
}

/**
 * @return list<string>
 */
function tagsFor(string $image): array
{
    $tags = [];

    foreach (imageReferences() as $reference) {
        if (preg_match('#^'.preg_quote($image, '#').':(.+)$#', $reference, $matches)) {
            $tags[] = $matches[1];
        }
    }

    return array_values(array_unique($tags));
}

it('declares no top-level named volumes in any Compose file', function (string $file) {
    expect(DeployConfig::compose($file)->toArray())->not->toHaveKey('volumes');
})->with(COMPOSE_FILES);

it('removes the Traefik override', function () {
    expect(file_exists(dirname(__DIR__, 2).'/docker-compose.override.traefik.yml'))->toBeFalse()
        ->and(file_get_contents(dirname(__DIR__, 2).'/README.md'))->not->toContain('traefik');
});

it('uses one tag per infrastructure image across Compose, Dockerfiles and workflows', function (string $image) {
    expect(tagsFor($image))->toHaveCount(1);
})->with(['mariadb', 'redis', 'node']);

it('runs the dev database from a bind mount with a separate root password', function () {
    $db = DeployConfig::compose(DEV_COMPOSE)->service('db');

    expect($db['volumes'])->toBe(['./docker/dbdata:/var/lib/mysql'])
        ->and($db['restart'])->toBe('unless-stopped')
        ->and($db['environment']['MARIADB_ROOT_PASSWORD'])->toContain('DB_ROOT_PASSWORD')
        ->and($db['environment']['MARIADB_PASSWORD'])->toContain('DB_PASSWORD:-');
});

it('gives every interpolated dev variable a default', function () {
    $contents = file_get_contents(dirname(__DIR__, 2).'/'.DEV_COMPOSE);

    expect(preg_match('/\$\{[A-Z_]+\}/', (string) $contents))->toBe(0);
});

it('runs npm through a real command and health-checks Vite', function () {
    $compose = DeployConfig::compose(DEV_COMPOSE);

    expect($compose->command('npm'))->toBe(['--version'])
        ->and($compose->healthcheck('vite'))->not->toBeEmpty();
});

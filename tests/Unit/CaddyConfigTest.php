<?php

declare(strict_types=1);

use Tests\Support\DeployConfig;

/**
 * @param  list<array{directive: string, children: list<mixed>}>  $blocks
 * @return array{directive: string, children: list<mixed>}
 */
function caddyBlock(array $blocks, string $directive): array
{
    foreach ($blocks as $block) {
        if ($block['directive'] === $directive) {
            return $block;
        }
    }

    throw new RuntimeException("Caddyfile has no [{$directive}] block.");
}

/**
 * @param  array{directive: string, children: list<mixed>}  $block
 * @return list<string>
 */
function caddyDirectives(array $block): array
{
    return array_column($block['children'], 'directive');
}

function mainSite(): array
{
    $site = null;

    foreach (DeployConfig::caddyfile('docker/Caddyfile') as $block) {
        if (str_starts_with($block['directive'], '{$CADDY_SERVER_SERVER_NAME}')) {
            $site = $block;
        }
    }

    return $site ?? throw new RuntimeException('Main site block missing.');
}

it('binds the admin API to localhost:2019', function () {
    $global = caddyBlock(DeployConfig::caddyfile('docker/Caddyfile'), '');

    expect(caddyDirectives($global))->toContain('admin localhost:2019')
        ->and(implode("\n", caddyDirectives($global)))->not->toContain('CADDY_SERVER_ADMIN');
});

it('serves metrics on a separate :9180 site', function () {
    $blocks = DeployConfig::caddyfile('docker/Caddyfile');

    expect(caddyDirectives(caddyBlock($blocks, ':9180')))->toBe(['metrics'])
        ->and(caddyDirectives(mainSite()))->not->toContain('metrics');
});

it('redacts the authorization query parameter on the request uri field', function () {
    $log = caddyBlock(mainSite()['children'], 'log');
    $filter = caddyBlock($log['children'], 'format filter');
    $fields = caddyBlock($filter['children'], 'fields');
    $uriQuery = caddyBlock($fields['children'], 'request>uri query');

    expect(caddyDirectives($uriQuery))->toContain('replace authorization REDACTED');
});

it('sets security headers and HSTS only for https APP_URL', function () {
    $site = mainSite();
    $headers = caddyDirectives(caddyBlock($site['children'], 'header'));

    expect($headers)->toContain('X-Content-Type-Options nosniff')
        ->and($headers)->toContain('X-Frame-Options DENY')
        ->and($headers)->toContain('Referrer-Policy strict-origin-when-cross-origin')
        ->and(implode("\n", $headers))->not->toContain('Strict-Transport-Security')
        ->and(implode("\n", caddyDirectives($site)))
        ->toContain('@https expression "{$APP_URL:http://localhost}".startsWith("https://")')
        ->toContain('header @https Strict-Transport-Security');
});

it('limits the request body to a configurable size defaulting to 20MB', function () {
    $body = caddyBlock(mainSite()['children'], 'request_body');

    expect(caddyDirectives($body))->toBe(['max_size {$CADDY_REQUEST_BODY_MAX_SIZE:20MB}']);
});

it('does not expose the admin API from either Dockerfile', function (string $dockerfile) {
    $arguments = implode("\n", array_column(DeployConfig::dockerfile($dockerfile), 'arguments'));

    expect($arguments)->not->toContain('--admin-host')
        ->and($arguments)->not->toContain('2019');
})->with(['docker/develop/Dockerfile', 'docker/production/Dockerfile']);

it('copies the Caddyfile into both images', function (string $dockerfile) {
    $copies = array_column(array_filter(
        DeployConfig::dockerfile($dockerfile),
        fn (array $entry): bool => $entry['instruction'] === 'COPY',
    ), 'arguments');

    expect($copies)->toContain('docker/Caddyfile /');
})->with(['docker/develop/Dockerfile', 'docker/production/Dockerfile']);

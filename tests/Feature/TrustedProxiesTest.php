<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

/**
 * @return TestResponse<Response>
 */
function proxiedScrape(string $remoteAddr, string $forwardedFor): TestResponse
{
    config(['prometheus.token' => '', 'prometheus.allowed_ips' => ['10.1.0.0/16']]);

    return test()
        ->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
        ->withHeader('X-Forwarded-For', $forwardedFor)
        ->get('/'.ltrim(config('prometheus.urls.default'), '/'));
}

it('honours the forwarded address only from a trusted proxy range', function (): void {
    config(['trustedproxy.proxies' => ['10.0.0.0/8']]);

    proxiedScrape('192.0.2.1', '10.1.1.1')->assertForbidden();
    proxiedScrape('10.0.0.5', '10.1.1.1')->assertOk();
});

it('trusts any proxy by default', function (): void {
    expect(config('trustedproxy.proxies'))->toBe('*');

    proxiedScrape('192.0.2.1', '10.1.1.1')->assertOk();
});

it('parses TRUSTED_PROXIES as a comma-separated list', function (string $value, string|array $expected): void {
    $previous = [$_ENV['TRUSTED_PROXIES'] ?? null, $_SERVER['TRUSTED_PROXIES'] ?? null];
    putenv("TRUSTED_PROXIES={$value}");
    $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $value;

    try {
        expect((require config_path('trustedproxy.php'))['proxies'])->toBe($expected);
    } finally {
        putenv('TRUSTED_PROXIES');
        $previous[0] === null ? $_ENV['TRUSTED_PROXIES'] = '*' : $_ENV['TRUSTED_PROXIES'] = $previous[0];
        $previous[1] === null ? $_SERVER['TRUSTED_PROXIES'] = '*' : $_SERVER['TRUSTED_PROXIES'] = $previous[1];
    }
})->with([
    'wildcard' => ['*', '*'],
    'cidr list' => ['10.0.0.0/8, 192.168.1.1', ['10.0.0.0/8', '192.168.1.1']],
    'single address' => ['172.16.0.2', ['172.16.0.2']],
]);

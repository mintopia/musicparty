<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::get('/metrics-test/ok', fn () => 'ok');
    Route::get('/metrics-test/boom', fn () => throw new RuntimeException('boom'));
    Route::get('/metrics-test/invalid', fn () => throw ValidationException::withMessages(['a' => ['bad']]));
});

function scrape(): string
{
    config(['prometheus.token' => 'scrape-token']);

    return test()
        ->withToken('scrape-token')
        ->get('/'.ltrim(config('prometheus.urls.default'), '/'))
        ->assertOk()
        ->getContent();
}

function metricsUrl(): string
{
    return '/'.ltrim(config('prometheus.urls.default'), '/');
}

it('refuses scrapes when no token or IP range is configured', function (): void {
    config(['prometheus.token' => '', 'prometheus.allowed_ips' => []]);

    $this->get(metricsUrl())->assertForbidden();
    $this->withToken('')->get(metricsUrl())->assertForbidden();
});

it('serves the right bearer token and refuses a wrong one', function (): void {
    config(['prometheus.token' => 'right', 'prometheus.allowed_ips' => []]);

    $this->withToken('right')->get(metricsUrl())->assertOk();
    $this->withToken('wrong')->get(metricsUrl())->assertForbidden();
    $this->get(metricsUrl())->assertForbidden();
});

it('serves an IP inside the allowed CIDR and refuses one outside', function (): void {
    config(['prometheus.token' => '', 'prometheus.allowed_ips' => ['10.1.0.0/16']]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->get(metricsUrl())->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '10.2.2.3'])->get(metricsUrl())->assertForbidden();
});

it('reports request, method, status and exception counters and Horizon metrics', function (): void {
    $this->get('/metrics-test/ok')->assertOk();
    $this->get('/metrics-test/missing')->assertNotFound();
    $this->get('/metrics-test/boom')->assertServerError();

    $output = scrape();

    expect($output)
        ->toContain('musicparty_http_requests_total 3')
        ->toContain('musicparty_http_requests_by_method_total{method="GET"} 3')
        ->toContain('musicparty_http_responses_by_status_total{code="200"} 1')
        ->toContain('musicparty_http_responses_by_status_total{code="404"} 1')
        ->toContain('musicparty_http_responses_by_status_total{code="500"} 1')
        ->toContain('musicparty_uncaught_exceptions_total 1')
        ->toContain('musicparty_horizon_');
});

it('does not count validation errors or 404s as uncaught exceptions', function (): void {
    $this->getJson('/metrics-test/invalid')->assertUnprocessable();
    $this->get('/metrics-test/missing')->assertNotFound();

    expect(scrape())->toContain('musicparty_uncaught_exceptions_total 0');
});

it('does not count scrapes and buckets unknown methods as OTHER', function (): void {
    scrape();
    $this->call('PURGE', '/metrics-test/ok');

    expect(scrape())
        ->toContain('musicparty_http_requests_total 1')
        ->toContain('musicparty_http_requests_by_method_total{method="OTHER"} 1');
});

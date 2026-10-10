<?php

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['database.redis.options.prefix' => 'metrics_test_'.getmypid().'_']);
    app()->forgetInstance('redis');
    Redis::clearResolvedInstance('redis');

    Route::get('/metrics-test/ok', fn () => 'ok');
    Route::get('/metrics-test/boom', fn () => throw new RuntimeException('boom'));
    Route::get('/metrics-test/invalid', fn () => throw ValidationException::withMessages(['a' => ['bad']]));
});

afterEach(function (): void {
    $prefix = (string) config('database.redis.options.prefix');
    foreach (Redis::keys('metrics.*') as $key) {
        Redis::del(substr($key, strlen($prefix)));
    }
});

function scrape(): string
{
    return test()->get('/'.ltrim(config('prometheus.urls.default'), '/'))->assertOk()->getContent();
}

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

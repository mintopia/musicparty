<?php

namespace App\Providers;

use App\Http\Middleware\MetricsCollector;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;
use Spatie\Prometheus\Collectors\Horizon\CurrentMasterSupervisorCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentProcessesPerQueueCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentWorkloadCollector;
use Spatie\Prometheus\Collectors\Horizon\FailedJobsPerHourCollector;
use Spatie\Prometheus\Collectors\Horizon\HorizonStatusCollector;
use Spatie\Prometheus\Collectors\Horizon\JobsPerMinuteCollector;
use Spatie\Prometheus\Collectors\Horizon\RecentJobsCollector;
use Spatie\Prometheus\Facades\Prometheus;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Prometheus::addCounter('HTTP Requests', fn (): int => (int) Redis::get('metrics.http.requests'), 'http_requests_total')
            ->helpText('The number of handled HTTP requests');

        Prometheus::addCounter('HTTP Methods', fn (): array => $this->methodCounts(), 'http_requests_by_method_total')
            ->helpText('The numbers of each HTTP method used')
            ->label('method');

        Prometheus::addCounter('HTTP Status Codes', fn (): array => $this->statusCounts(), 'http_responses_by_status_total')
            ->helpText('The numbers of each HTTP status code returned')
            ->label('code');

        Prometheus::addCounter('Uncaught Exceptions', fn (): int => (int) Redis::get('metrics.exceptions'), 'uncaught_exceptions_total')
            ->helpText('The number of uncaught exceptions');
    }

    public function boot(): void
    {
        $this->registerHorizonCollectors();
    }

    /**
     * @return array<int, array{0: int, 1: array<int, string>}>
     */
    protected function methodCounts(): array
    {
        return $this->countsFor('metrics.http.method', [...MetricsCollector::METHODS, MetricsCollector::OTHER_METHOD]);
    }

    /**
     * @return array<int, array{0: int, 1: array<int, string>}>
     */
    protected function statusCounts(): array
    {
        $codes = array_map('strval', Redis::smembers(MetricsCollector::STATUS_CODES_KEY));
        sort($codes);

        return $this->countsFor('metrics.http.status', $codes);
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array{0: int, 1: array<int, string>}>
     */
    protected function countsFor(string $prefix, array $names): array
    {
        if ($names === []) {
            return [];
        }

        $values = Redis::mget(array_map(fn (string $name): string => "{$prefix}.{$name}", $names));
        $result = [];
        foreach ($names as $index => $name) {
            $result[] = [(int) ($values[$index] ?? 0), [$name]];
        }

        return $result;
    }

    public function registerHorizonCollectors(): self
    {
        Prometheus::registerCollectorClasses([
            CurrentMasterSupervisorCollector::class,
            CurrentProcessesPerQueueCollector::class,
            CurrentWorkloadCollector::class,
            FailedJobsPerHourCollector::class,
            HorizonStatusCollector::class,
            JobsPerMinuteCollector::class,
            RecentJobsCollector::class,
        ]);

        return $this;
    }
}

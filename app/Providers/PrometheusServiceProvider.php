<?php

namespace App\Providers;

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
    public function register()
    {
        // HTTP Metrics
        Prometheus::addGauge('HTTP Requests')
            ->helpText('The number of handled HTTP requests')
            ->value(function () {
                return Redis::get("metrics.http.requests") ?? 0;
            });

        Prometheus::addGauge('HTTP Methods')
            ->helpText('The numbers of each HTTP method used')
            ->label('method')
            ->value(function () {
                return $this->getMultipleFromRedis('metrics.http.method');
            });

        Prometheus::addGauge('HTTP Status Codes')
            ->helpText('The numbers of each HTTP status code returned')
            ->label('code')
            ->value(function () {
                return $this->getMultipleFromRedis('metrics.http.status');
            });

        Prometheus::addGauge('Uncaught Exceptions')
            ->helpText('The number of uncaught exceptions')
            ->value(function () {
                return Redis::get("metrics.exceptions") ?? 0;
            });
    }

    protected function getMultipleFromRedis(string $prefix): array
    {
        $keys = Redis::keys("{$prefix}.*");
        $lookup = [];
        $names = [];
        foreach ($keys as $key) {
            $parts = explode('.', $key);
            $name = end($parts);
            $names[] = $name;
            $lookup[] = "{$prefix}.{$name}";
        }
        if (!$lookup) {
            return [];
        }
        $values = Redis::mget($lookup);
        $result = [];
        foreach ($names as $index => $name) {
            $value = $values[$index] ?? 0;
            $result[] = [
                $value, [$name],
            ];
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

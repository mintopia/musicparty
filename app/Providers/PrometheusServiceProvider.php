<?php

namespace App\Providers;

use Illuminate\Support\Facades\Queue;
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
            ->value(function() {
                return Redis::get('metrics.http.requests') ?? 0;
            });

        Prometheus::addGauge('HTTP Methods')
            ->helpText('The numbers of each HTTP method used')
            ->label('method')
            ->value(function() {
                return $this->getMultipleFromRedis('metrics.http.method');
            });

        Prometheus::addGauge('HTTP Status Codes')
            ->helpText('The numbers of each HTTP status code returned')
            ->label('code')
            ->value(function() {
                return $this->getMultipleFromRedis('metrics.http.status');
            });

        Prometheus::addGauge('Votes')
            ->helpText('The number of votes for a song')
            ->label('party')
            ->value(function() {
                return $this->getMultipleFromRedis('metrics.votes');
            });

        Prometheus::addGauge('Uncaught Exceptions')
            ->helpText('The number of uncaught exceptions')
            ->value(function() {
                return Redis::get('metrics.exceptions') ?? 0;
            });

        Prometheus::addGauge('Jobs Successfully Processed')
            ->helpText('The number of jobs processed')
            ->label('job')
            ->value(function() {
                return $this->getMultipleFromRedis('metrics.jobs.processed');
            });

        Prometheus::addGauge('Queue Jobs Successfully Processed')
            ->helpText('The number of jobs processed by queue')
            ->label('queue')
            ->value(function() {
                return $this->getMultipleFromRedis('metrics.jobs.processed');
            });

        Prometheus::addGauge('Jobs Failed')
            ->helpText('The number of jobs failed by job')
            ->label('job')
            ->value(function() {
                return $this->getMultipleFromRedis('metrics.jobs.failed');
            });

        Prometheus::addGauge('Queue Jobs Failed')
            ->helpText('The number of jobs failed by queue')
            ->label('queue')
            ->value(function() {
                return $this->getMultipleFromRedis('metrics.jobs.failed');
            });

        Prometheus::addGauge('Queue Size')
            ->helpText('The number of jobs processed by queue')
            ->label('queue')
            ->value(function() {
                $result = [];
                foreach (config('prometheus.queues') as $queueName) {
                    $size = Queue::size($queueName) ?? 0;
                    return [$size, [$queueName]];
                }
                return $result;
            });
    }

    protected function getMultipleFromRedis(string $prefix): array
    {
        $keys = Redis::keys("{$prefix}.*");
        if (!$keys) {
            return [];
        }
        $lookup = [];
        $names = [];
        foreach ($keys as $key) {
            $parts = explode('.', $key);
            $name = end($parts);
            $names[] = $name;
            $lookup[] = "{$prefix}.{$name}";
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

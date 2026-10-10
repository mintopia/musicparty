<?php

namespace App\Providers;

use App\Domain\Stats\Actions\BuildLiveStatsMetrics;
use App\Http\Middleware\MetricsCollector;
use App\Support\Metrics\CounterStore;
use App\Support\Metrics\RedisCounterStore;
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
        $this->app->singleton(CounterStore::class, RedisCounterStore::class);

        Prometheus::addCounter('HTTP Requests', fn (): int => app(CounterStore::class)->get('metrics.http.requests'), 'http_requests_total')
            ->helpText('The number of handled HTTP requests');

        Prometheus::addCounter('HTTP Methods', fn (): array => $this->methodCounts(), 'http_requests_by_method_total')
            ->helpText('The numbers of each HTTP method used')
            ->label('method');

        Prometheus::addCounter('HTTP Status Codes', fn (): array => $this->statusCounts(), 'http_responses_by_status_total')
            ->helpText('The numbers of each HTTP status code returned')
            ->label('code');

        Prometheus::addCounter('Uncaught Exceptions', fn (): int => app(CounterStore::class)->get('metrics.exceptions'), 'uncaught_exceptions_total')
            ->helpText('The number of uncaught exceptions');

        $this->app->scoped(BuildLiveStatsMetrics::class);

        $gauges = [
            ['Parties', 'parties', 'The number of Parties in each state', ['state']],
            ['Party Members', 'party_members', 'Members of a Live or Paused Party, excluding Banned ones', ['party']],
            ['Party Queue Length', 'party_queue_length', 'Queued Requests in a Live or Paused Party', ['party']],
            ['Party Time Played', 'party_time_played_seconds', 'Seconds of music played in a Live or Paused Party', ['party']],
            ['Party Top Track Plays', 'party_top_track_plays', 'Plays of the top Tracks in a Live or Paused Party', ['party', 'rank', 'track']],
            ['Party Top Requester Plays', 'party_top_requester_plays', 'Plays of the top requesters in a Live or Paused Party', ['party', 'rank', 'member']],
            ['Party Most Upvoted Score', 'party_most_upvoted_score', 'Score of the most upvoted Tracks in a Live or Paused Party', ['party', 'rank', 'track']],
            ['Party Most Downvoted Score', 'party_most_downvoted_score', 'Score of the most downvoted Tracks in a Live or Paused Party', ['party', 'rank', 'track']],
        ];

        foreach ($gauges as [$label, $name, $help, $labels]) {
            Prometheus::addGauge($label, fn (): array => app(BuildLiveStatsMetrics::class)->series($name), $name)
                ->helpText($help)
                ->labels($labels);
        }
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
        $codes = app(CounterStore::class)->members(MetricsCollector::STATUS_CODES_KEY);
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

        $values = app(CounterStore::class)->many(array_values(array_map(fn (string $name): string => "{$prefix}.{$name}", $names)));
        $result = [];
        foreach ($names as $index => $name) {
            $result[] = [$values[$index], [$name]];
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

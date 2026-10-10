<?php

namespace Tests;

use App\Support\Metrics\CounterStore;
use App\Support\Metrics\InMemoryCounterStore;
use App\Support\RateLimiting\Bucket;
use App\Support\RateLimiting\Testing\FakeLeakyBucket;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Tests\Support\TestingServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(TestingServiceProvider::class);
        $this->withoutVite();
        $this->app->singleton(Bucket::class, FakeLeakyBucket::class);
        $this->app->singleton(CounterStore::class, InMemoryCounterStore::class);
        $this->stubHorizonRepositories();
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function setUpTraits(): array
    {
        $this->assertInMemorySqlite();

        return parent::setUpTraits();
    }

    private function stubHorizonRepositories(): void
    {
        $this->mock(MasterSupervisorRepository::class, fn ($mock) => $mock->shouldReceive('all')->andReturn([]));
        $this->mock(WorkloadRepository::class, fn ($mock) => $mock->shouldReceive('get')->andReturn([]));
        $this->mock(MetricsRepository::class, fn ($mock) => $mock->shouldReceive('jobsProcessedPerMinute')->andReturn(0));
        $this->mock(JobRepository::class, fn ($mock) => $mock->shouldReceive('countRecent', 'countRecentlyFailed')->andReturn(0));
    }

    public function assertInMemorySqlite(): void
    {
        $name = (string) config('database.default');
        $database = config("database.connections.{$name}.database");

        if (config("database.connections.{$name}.driver") !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException("Tests run only on in-memory SQLite, but the default connection is [{$name}]. Unset DB_CONNECTION and DB_DATABASE.");
        }
    }
}

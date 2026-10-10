<?php

namespace Tests;

use App\Support\RateLimiting\Bucket;
use App\Support\RateLimiting\Testing\FakeLeakyBucket;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->app->singleton(Bucket::class, FakeLeakyBucket::class);
    }
}

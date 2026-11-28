<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * migrate:fresh refuses to run when the environment is production, so the profile migrates as testing and switches back.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits(): array
    {
        if (! self::usesProductionProfile()) {
            return parent::setUpTraits();
        }

        $this->app['env'] = 'testing';
        try {
            return parent::setUpTraits();
        } finally {
            $this->app['env'] = 'production';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}

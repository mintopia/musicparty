<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Queue::after(function(JobProcessed $event) {
            $name = $event->job->resolveName();
            $queue = $event->job->getQueue();
            try {
                if ($event->job->hasFailed()) {
                    Redis::incr("metrics.jobs.failed.{$name}", 1);
                    Redis::incr("metrics.queues.failed.{$queue}", 1);
                } else {
                    Redis::incr("metrics.jobs.processed.{$name}", 1);
                    Redis::incr("metrics.queues.processed.{$queue}", 1);
                }
            } catch (\Exception $ex) {
                // Do Nothing
            }
        });
    }
}

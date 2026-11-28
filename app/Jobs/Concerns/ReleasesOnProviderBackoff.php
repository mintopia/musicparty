<?php

namespace App\Jobs\Concerns;

use App\Exceptions\ProviderBackoffException;
use Illuminate\Support\Facades\Log;

trait ReleasesOnProviderBackoff
{
    public const int MAX_BACKOFF_RELEASE_SECONDS = 60;

    /**
     * Release for Retry-After up to 60s; longer waits return for the scheduler to requeue.
     */
    protected function releaseForBackoff(ProviderBackoffException $e): void
    {
        $seconds = $e->retryAfterSeconds();
        if ($seconds > self::MAX_BACKOFF_RELEASE_SECONDS) {
            Log::info("{$this->party}: backoff of {$seconds}s is too long to hold, returning");

            return;
        }

        Log::info("{$this->party}: backoff active, releasing for {$seconds}s");
        $this->release($seconds);
    }
}

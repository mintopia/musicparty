<?php

namespace App\Jobs;

use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Jobs\Concerns\ReleasesOnProviderBackoff;
use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\MusicProviderFactory;
use App\Services\PlaybackSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PartyUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReleasesOnProviderBackoff;
    use SerializesModels;

    public const int WEBHOOK_DEBOUNCE_RELEASE_SECONDS = 6;

    public bool $deleteWhenMissingModels = true;

    public int $maxExceptions = 5;

    /**
     * Seconds to wait before retrying after a thrown exception, growing exponentially.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [2, 5, 15, 30];
    }

    /**
     * Bounded by time, not a try count, so releases do not use up retries.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(5);
    }

    /**
     * Create a new job instance.
     */
    public function __construct(protected Party $party, protected PlaybackSource $source = PlaybackSource::Polling)
    {
        $this->onQueue('partyupdates');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->source === PlaybackSource::Webhook) {
            Cache::forget($this->party->webhookPendingKey());
        }

        try {
            $this->update(app(PlaybackSync::class));
        } catch (ProviderBackoffException $e) {
            $this->releaseForBackoff($e);
        } catch (ProviderRelinkRequiredException) {
            Log::warning("{$this->party}: provider needs re-linking, stopping poll chain");
        }
    }

    protected function update(PlaybackSync $sync): void
    {
        if (app(MusicProviderFactory::class)->partyNeedsRelink($this->party)) {
            Log::warning("{$this->party}: Provider needs re-linking, stopping poll chain");

            return;
        }

        if (! $this->party->poll) {
            Log::debug("{$this->party}: Updating from PartyUpdate job");
            if (! $sync->sync($this->party, $this->source)) {
                $this->release(2);
            }

            return;
        }

        if ($this->party->updatedWithinDebounce()) {
            if ($this->source === PlaybackSource::Webhook) {
                Log::debug("{$this->party}: Webhook update inside debounce, releasing for ".self::WEBHOOK_DEBOUNCE_RELEASE_SECONDS.'s');
                $this->release(self::WEBHOOK_DEBOUNCE_RELEASE_SECONDS);

                return;
            }

            Log::debug("{$this->party}: No further processing");

            return;
        }

        Log::debug("{$this->party}: Updating from PartyUpdate job");
        if (! $sync->sync($this->party, $this->source)) {
            $this->release(2);

            return;
        }

        $delay = $sync->nextUpdateDelay($this->party);
        if ($delay === null) {
            Log::debug("{$this->party}: No further update required");
        } else {
            Log::debug("{$this->party}: Next update in {$delay}s");
            PartyUpdate::dispatch($this->party)->delay($delay);
        }
    }
}

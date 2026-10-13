<?php

namespace App\Jobs;

use App\Models\Party;
use App\Services\Spotify\SpotifyBackoffException;
use App\Services\Spotify\SpotifyRelinkRequiredException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PartyUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
     * Lock-contention and Spotify-backoff releases also count as attempts, so retries are bounded by time and thrown exceptions rather than a try count.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(5);
    }

    /**
     * Create a new job instance.
     */
    public function __construct(protected Party $party)
    {
        $this->onQueue('partyupdates');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->update();
        } catch (SpotifyBackoffException $e) {
            Log::info("{$this->party}: Spotify backoff active, releasing for {$e->retryAfterSeconds}s");
            $this->release($e->retryAfterSeconds);
        } catch (SpotifyRelinkRequiredException) {
            Log::warning("{$this->party}: Spotify needs re-linking, stopping poll chain");
        }
    }

    protected function update(): void
    {
        if ($this->party->user->spotifyNeedsRelink()) {
            Log::warning("{$this->party}: Spotify needs re-linking, stopping poll chain");

            return;
        }

        if (! $this->party->poll) {
            Log::debug("{$this->party}: Updating from PartyUpdate job");
            if (! $this->party->tryUpdateState()) {
                $this->release(2);
            }

            return;
        }

        $cutoff = now()->subSeconds(5);
        if ($this->party->last_updated_at > $cutoff) {
            Log::debug("{$this->party}: No further processing");

            return;
        }

        Log::debug("{$this->party}: Updating from PartyUpdate job");
        if (! $this->party->tryUpdateState()) {
            $this->release(2);

            return;
        }

        $delay = $this->party->getNextUpdateDelay();
        if ($delay === null) {
            Log::debug("{$this->party}: No further update required");
        } else {
            Log::debug("{$this->party}: Next update in {$delay}s");
            PartyUpdate::dispatch($this->party)->delay($delay);
        }
    }
}

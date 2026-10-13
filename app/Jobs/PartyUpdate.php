<?php

namespace App\Jobs;

use App\Models\Party;
use App\Services\Spotify\SpotifyBackoffException;
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
        }
    }

    protected function update(): void
    {
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

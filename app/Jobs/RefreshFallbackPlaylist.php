<?php

namespace App\Jobs;

use App\Models\Party;
use App\Services\FallbackPlaylist;
use App\Services\Spotify\SpotifyBackoffException;
use App\Services\Spotify\SpotifyRelinkRequiredException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshFallbackPlaylist implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $maxExceptions = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(5);
    }

    public function __construct(protected Party $party)
    {
        $this->onQueue('partyupdates');
    }

    public function handle(FallbackPlaylist $fallbackPlaylist): void
    {
        try {
            $fallbackPlaylist->refresh($this->party);
        } catch (SpotifyBackoffException $e) {
            Log::info("{$this->party}: Spotify backoff active, releasing for {$e->retryAfterSeconds}s");
            $this->release($e->retryAfterSeconds);
        } catch (SpotifyRelinkRequiredException) {
            Log::warning("{$this->party}: Spotify relink required, fallback playlist not refreshed");
        }
    }
}

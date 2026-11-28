<?php

namespace App\Jobs;

use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Jobs\Concerns\ReleasesOnProviderBackoff;
use App\Models\Party;
use App\Services\Playback\ContextPlaylist;
use App\Services\PlaybackSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EmptyContextPlaylist implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use ReleasesOnProviderBackoff;
    use SerializesModels;

    public const int LOCK_BUSY_RELEASE_SECONDS = 2;

    public bool $deleteWhenMissingModels = true;

    public int $maxExceptions = 3;

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(5);
    }

    public function __construct(protected Party $party)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->party->id;
    }

    public function handle(ContextPlaylist $context, PlaybackSync $sync): void
    {
        try {
            $ran = $sync->withLock($this->party, fn () => $context->enforceEmpty($this->party));
            if (! $ran) {
                Log::info("{$this->party}: sync lock busy, releasing for ".self::LOCK_BUSY_RELEASE_SECONDS.'s');
                $this->release(self::LOCK_BUSY_RELEASE_SECONDS);
            }
        } catch (ProviderBackoffException $e) {
            $this->releaseForBackoff($e);
        } catch (ProviderRelinkRequiredException) {
            Log::warning("{$this->party}: relink required, context playlist not checked");
        }
    }
}

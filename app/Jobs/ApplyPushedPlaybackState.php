<?php

namespace App\Jobs;

use App\Services\Music\Data\PlaybackState;
use App\Services\Playback\PushedStateRouter;
use App\Services\PlaybackSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ApplyPushedPlaybackState implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 4;

    public int $maxExceptions = 1;

    public int $receivedAtMs;

    public function __construct(
        public string $channel,
        public string $event,
        public mixed $data,
        ?int $receivedAtMs = null,
    ) {
        $this->receivedAtMs = $receivedAtMs ?? now()->getTimestampMs();
        $this->onQueue('partyupdates');
    }

    public function handle(PushedStateRouter $router, PlaybackSync $sync): void
    {
        $route = $router->route($this->channel);
        if ($route === null) {
            Log::debug("Dropping pushed frame on unknown channel {$this->channel}");

            return;
        }

        [$mapper, $code] = $route;
        $party = $router->findParty($code);
        if ($party === null) {
            Log::debug("Dropping pushed frame for unknown Party {$code} on {$this->channel}");

            return;
        }

        $data = is_string($this->data) ? json_decode($this->data, true) : $this->data;
        if (! is_array($data)) {
            Log::debug("{$party}: Dropping pushed frame {$this->event} with malformed data");

            return;
        }

        $map = function () use ($mapper, $party, $data): ?PlaybackState {
            $state = $mapper->map($party, $this->event, $data);
            if ($state === null) {
                Log::debug("{$party}: Dropping pushed frame {$this->event} that carries no playback state");
            }

            return $state;
        };

        if ($sync->apply($party, $map, $this->receivedAtMs)) {
            return;
        }
        if ($this->attempts() < $this->tries) {
            $this->release(1);

            return;
        }
        Log::warning("{$party}: Lost pushed frame {$this->event} because another sync holds the lock");
    }
}

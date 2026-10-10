<?php

namespace App\Jobs;

use App\Domain\Playback\Contracts\HandlesPlayerFrames;
use App\Domain\Playback\PartyPlayers;
use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class ProcessPlayerFrame implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const DISCARDED_COUNTER = 'player-frames.discarded';

    /**
     * @param  array<string, mixed>  $frame
     */
    public function __construct(public readonly string $partyCode, public readonly array $frame)
    {
        $this->onQueue('player');
    }

    public int $lockWaitSeconds = 3;

    public function handle(PartyPlayers $players): void
    {
        try {
            Cache::lock('player-frame:'.$this->partyCode, 5)->block($this->lockWaitSeconds, fn () => $this->process($players));
        } catch (LockTimeoutException) {
            $this->release(1);
        }
    }

    private function process(PartyPlayers $players): void
    {
        $party = Party::findByCode($this->partyCode);
        $player = $party === null ? null : $players->for($party);

        if (! $player instanceof HandlesPlayerFrames || ! $player->handleFrame($this->frame)) {
            Cache::add(self::DISCARDED_COUNTER, 0);
            Cache::increment(self::DISCARDED_COUNTER);
        }
    }
}

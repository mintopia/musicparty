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

    private const SEQUENCE_TTL_SECONDS = 86400;

    /**
     * @param  array<string, mixed>  $frame
     */
    public function __construct(public readonly string $partyCode, public readonly array $frame, public readonly ?int $sequence = null)
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

    public static function sequenceKey(string $partyCode): string
    {
        return 'player-frame-seq:'.$partyCode;
    }

    private function process(PartyPlayers $players): void
    {
        if ($this->isStale()) {
            $this->countDiscard();

            return;
        }

        $party = Party::findByCode($this->partyCode);
        $player = $party === null ? null : $players->for($party);

        if (! $player instanceof HandlesPlayerFrames || ! $player->handleFrame($this->frame)) {
            $this->countDiscard();
        }

        if ($this->sequence !== null) {
            Cache::put(self::appliedKey($this->partyCode), $this->sequence, self::SEQUENCE_TTL_SECONDS);
        }
    }

    private static function appliedKey(string $partyCode): string
    {
        return 'player-frame-applied:'.$partyCode;
    }

    private function isStale(): bool
    {
        return $this->sequence !== null && $this->sequence <= (int) Cache::get(self::appliedKey($this->partyCode), 0);
    }

    private function countDiscard(): void
    {
        Cache::add(self::DISCARDED_COUNTER, 0);
        Cache::increment(self::DISCARDED_COUNTER);
    }
}

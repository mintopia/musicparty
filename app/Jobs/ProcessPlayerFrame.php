<?php

namespace App\Jobs;

use App\Domain\Playback\Contracts\HandlesPlayerFrames;
use App\Domain\Playback\PartyPlayers;
use App\Models\Party;
use Illuminate\Bus\Queueable;
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

    private const STATE_TTL_SECONDS = 86400;

    private const FRAME_TTL_SECONDS = 300;

    private const LOCK_SECONDS = 30;

    public function __construct(public readonly string $partyCode)
    {
        $this->onQueue('player');
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    public static function enqueue(string $partyCode, array $frame): int
    {
        $counterKey = self::key('latest', $partyCode);
        Cache::add($counterKey, 0, self::STATE_TTL_SECONDS);
        $sequence = (int) Cache::increment($counterKey);

        Cache::put(self::frameKey($partyCode, $sequence), $frame, self::FRAME_TTL_SECONDS);
        self::dispatch($partyCode);

        return $sequence;
    }

    public function handle(PartyPlayers $players): void
    {
        $lock = Cache::lock(self::key('lock', $this->partyCode), self::LOCK_SECONDS);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->drain($players);
        } finally {
            $lock->release();
        }

        if ($this->hasPendingFrames()) {
            self::dispatch($this->partyCode);
        }
    }

    private function drain(PartyPlayers $players): void
    {
        $party = Party::findByCode($this->partyCode);
        $player = $party === null ? null : $players->for($party);

        while (($sequence = $this->nextSequence()) !== null) {
            $frame = Cache::pull(self::frameKey($this->partyCode, $sequence));
            Cache::put(self::key('applied', $this->partyCode), $sequence, self::STATE_TTL_SECONDS);

            if (! is_array($frame)) {
                continue;
            }

            if (! $player instanceof HandlesPlayerFrames || ! $player->handleFrame($frame)) {
                Cache::add(self::DISCARDED_COUNTER, 0);
                Cache::increment(self::DISCARDED_COUNTER);
            }
        }
    }

    private function nextSequence(): ?int
    {
        $next = (int) Cache::get(self::key('applied', $this->partyCode), 0) + 1;

        return $next <= (int) Cache::get(self::key('latest', $this->partyCode), 0) ? $next : null;
    }

    private function hasPendingFrames(): bool
    {
        return $this->nextSequence() !== null;
    }

    private static function key(string $name, string $partyCode): string
    {
        return "player-frame-{$name}:{$partyCode}";
    }

    private static function frameKey(string $partyCode, int $sequence): string
    {
        return "player-frame:{$partyCode}:{$sequence}";
    }
}

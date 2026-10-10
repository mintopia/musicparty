<?php

namespace App\Domain\Playback\Jobs;

use App\Domain\Party\Models\Party;
use App\Domain\Playback\Contracts\HandlesPlayerFrames;
use App\Domain\Playback\PartyPlayers;
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

    private const int FRAME_TTL_SECONDS = 300;

    private const int LOCK_SECONDS = 30;

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
        Cache::add($counterKey, 0);
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

            if ($frame === null && ! $this->hasFrameAfter($sequence)) {
                return;
            }

            Cache::forever(self::key('applied', $this->partyCode), $sequence);

            if (! is_array($frame) || ! $player instanceof HandlesPlayerFrames || ! $player->handleFrame($frame)) {
                Cache::add(self::DISCARDED_COUNTER, 0);
                Cache::increment(self::DISCARDED_COUNTER);
            }
        }
    }

    private function nextSequence(): ?int
    {
        $latest = (int) Cache::get(self::key('latest', $this->partyCode), 0);
        $applied = (int) Cache::get(self::key('applied', $this->partyCode), 0);

        if ($applied > $latest) {
            Cache::forever(self::key('applied', $this->partyCode), $applied = 0);
        }

        return $applied + 1 <= $latest ? $applied + 1 : null;
    }

    private function hasFrameAfter(int $sequence): bool
    {
        $latest = (int) Cache::get(self::key('latest', $this->partyCode), 0);

        for ($next = $sequence + 1; $next <= $latest; $next++) {
            if (Cache::has(self::frameKey($this->partyCode, $next))) {
                return true;
            }
        }

        return false;
    }

    private function hasPendingFrames(): bool
    {
        $next = $this->nextSequence();

        return $next !== null && Cache::has(self::frameKey($this->partyCode, $next));
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

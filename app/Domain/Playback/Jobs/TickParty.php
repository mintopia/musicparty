<?php

namespace App\Domain\Playback\Jobs;

use App\Domain\Party\Models\Party;
use App\Domain\Playback\PlaybackCoordinator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class TickParty implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const int LOCK_SECONDS = 30;

    public int $tries = 1;

    public int $timeout = 20;

    public int $uniqueFor = self::LOCK_SECONDS;

    public function __construct(public readonly string $partyCode)
    {
        $this->onQueue('ticks');
    }

    public function uniqueId(): string
    {
        return $this->partyCode;
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping("tick:{$this->partyCode}")->expireAfter(self::LOCK_SECONDS)->dontRelease()];
    }

    public function handle(PlaybackCoordinator $coordinator): void
    {
        $party = Party::findByCode($this->partyCode);

        if ($party !== null) {
            $coordinator->tick($party);
        }
    }
}

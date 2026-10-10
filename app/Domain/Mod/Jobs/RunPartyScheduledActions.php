<?php

namespace App\Domain\Mod\Jobs;

use App\Domain\Mod\Actions\RunScheduledActions;
use App\Domain\Party\Models\Party;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RunPartyScheduledActions implements ShouldBeUnique, ShouldQueue
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
        return [new WithoutOverlapping("mods:{$this->partyCode}")->expireAfter(self::LOCK_SECONDS)->dontRelease()];
    }

    public function handle(RunScheduledActions $run): void
    {
        $party = Party::findByCode($this->partyCode);

        if ($party !== null) {
            $run($party);
        }
    }
}

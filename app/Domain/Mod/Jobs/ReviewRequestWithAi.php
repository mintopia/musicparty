<?php

namespace App\Domain\Mod\Jobs;

use App\Domain\Mod\AiReview\AiRequestReviewer;
use App\Domain\Party\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class ReviewRequestWithAi implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public int $partyId, public int $requestId)
    {
        $this->onQueue('mods-ai');
    }

    public function handle(AiRequestReviewer $reviewer): void
    {
        $party = Party::query()->find($this->partyId);

        if ($party !== null) {
            $reviewer->review($party, $this->requestId);
        }
    }

    public function failed(Throwable $exception): void
    {
        $party = Party::query()->find($this->partyId);

        if ($party !== null) {
            app(AiRequestReviewer::class)->fail($party, $this->requestId, $exception->getMessage());
        }
    }
}

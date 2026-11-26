<?php

namespace App\Jobs;

use App\Models\Party;
use App\Mods\AiRequestReview\PartyVibeSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshPartyVibeSummary implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const DEBOUNCE_SECONDS = 60;

    public int $uniqueFor = 120;

    public function __construct(public Party $party)
    {
        $this->onQueue('default');
    }

    public static function debounced(Party $party): void
    {
        self::dispatch($party)->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
    }

    public function uniqueId(): string
    {
        return (string) $this->party->id;
    }

    public function handle(PartyVibeSummary $summary): void
    {
        $summary->refresh($this->party);
    }
}

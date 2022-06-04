<?php

namespace App\Jobs;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PartyUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(protected Party $party)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->party->updateState();
        $delay = $this->party->getNextUpdateDelay();
        if ($delay === null) {
            Log::debug("[Party:{$this->party->id}] No further update required");
        } else {
            Log::debug("[Party:{$this->party->id}] Next update in {$delay}s");
            PartyUpdate::dispatch($this->party)->delay($delay);
        }
    }
}

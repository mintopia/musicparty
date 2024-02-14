<?php

namespace App\Jobs;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class PartyUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $uniqueFor = 3600;
 
    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->party->id;
    }
    
    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->party->id)->dontRelease()->expireAfter(120)];
    }
    
    /**
     * Create a new job instance.
     */
    public function __construct(protected Party $party)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->party->updateState();
        $delay = $this->party->getNextUpdateDelay();
        if ($delay === null) {
            Log::debug("{$this->party}: No further update required");
        } else {
            Log::debug("{$this->party}: Next update in {$delay}s");
            PartyUpdate::dispatch($this->party)->delay($delay);
        }
    }
}

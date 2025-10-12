<?php

namespace App\Jobs;

use App\Events\Party\PlayYouTubeVideoEvent;
use App\Models\Party;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;

class PartyPlayYouTubeVideo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Party $party, protected string $videoId)
    {
        $this->onQueue('partyupdates');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        PlayYouTubeVideoEvent::dispatch($this->party, $this->videoId)->dispatch();
    }
}

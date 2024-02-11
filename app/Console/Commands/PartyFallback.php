<?php

namespace App\Console\Commands;

use App\Jobs\PartyUpdate;
use App\Models\Party;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PartyFallback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:fallback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force parties to be updated if they need updating';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $parties = Party::whereActive(true)->where(function ($query) {
            $cutoff = now()->subSeconds(90);
            $query->where('last_updated_at', '<=', $cutoff)->orWhereNull('last_updated_at');
        })->get();

        foreach ($parties as $party) {
            Log::info("{$party}: Overdue an update, triggering fallback");
            PartyUpdate::dispatch($party);
        }
        return self::SUCCESS;
    }
}

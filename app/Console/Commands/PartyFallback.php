<?php

namespace App\Console\Commands;

use App\Jobs\PartyUpdate;
use App\Models\Party;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SpotifyWebAPI\SpotifyWebAPI;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $parties = Party::where(function ($query) {
            $cutoff = Carbon::now()->subSeconds(90);
            $query->where('active', true);
            $query->where('state_updated_at', '<=', $cutoff)->orWhereNull('state_updated_at');
        })->get();

        foreach ($parties as $party) {
            Log::info("[Party:{$party->id}] Overdue an update, triggering fallback");
            PartyUpdate::dispatch($party);
        }
        return self::SUCCESS;
    }
}

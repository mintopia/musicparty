<?php

namespace App\Console\Commands;

use App\Models\Party;
use Illuminate\Console\Command;
use SpotifyWebAPI\SpotifyWebAPI;

class PartyUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:update';

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
        $parties = Party::all();
        foreach ($parties as $party) {
            $party->updateState();
        }
    }
}

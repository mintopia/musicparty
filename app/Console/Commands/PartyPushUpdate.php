<?php

namespace App\Console\Commands;

use App\Models\Party;
use Illuminate\Console\Command;

class PartyPushUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:pushupdate {party}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push a websocket update for the party';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = $this->input->getArgument('party');
        $party = Party::whereCode($code)->first();
        if (!$party) {
            $this->error("Unable to find a party with code {$code}");
            return self::FAILURE;
        }
        $party->pushUpdate();
        return self::SUCCESS;
    }
}

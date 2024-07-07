<?php

namespace App\Console\Commands;

use App\Models\Party;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PartyCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:check {code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Check all parties to see if they're broken and fix them";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = $this->argument('code');
        if ($code !== null) {
            $party = Party::whereCode($code)->first();
            if (!$party) {
                $this->error("Unable to find party with code {$code}");
                return self::FAILURE;
            }
            return $this->processParty($party);
        }

        $parties = Party::whereActive(true)->chunk(50, function($chunk) {
            foreach ($chunk as $party) {
                $this->processParty($party);
            }
        });

        return self::SUCCESS;
    }

    protected function processParty(Party $party): int
    {
        $this->output->writeln("{$party}: Checking to see if broken");
        $party->fixPlaylist();
        $this->output->writeln("{$party}: Done");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Party;
use Illuminate\Console\Command;

class PartyCalculateTrust extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:calculatetrust {party}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate trust scores for a party';

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
        $party->calculateTrustScores();
        return self::SUCCESS;
    }
}

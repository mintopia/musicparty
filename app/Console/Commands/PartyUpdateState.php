<?php

namespace App\Console\Commands;

use App\Models\Party;
use Illuminate\Console\Command;

class PartyUpdateState extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:update {party : The code of the party}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update party state';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $code = $this->argument('party');
        $party = Party::whereCode($code)->first();
        if (!$party) {
            $this->output->error('Party not found');
            return self::FAILURE;
        }

        $this->output->writeln("[Party:{$party->id}] {$party->code}] Updating State\n");
        $party->updateState();
        return self::SUCCESS;
    }
}

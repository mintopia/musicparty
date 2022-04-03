<?php

namespace App\Console\Commands;

use App\Models\Party;
use Illuminate\Console\Command;

class PartyFixPlaylist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:fixplaylist {party : The code of the party}';

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
        $code = $this->argument('party');
        $party = Party::whereCode($code)->first();
        if (!$party) {
            $this->output->error('Party not found');
            return 1;
        }
        $party->fixPlaylist();
        $this->output->writeln("Recreated playlist for [Party:{$party->id}] {$party->name}");
        return 0;
    }
}

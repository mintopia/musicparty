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
    protected $signature = 'party:fixplaylist {party : The code of the party} {--f|force : Force playback}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix a playlist';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $code = $this->argument('party');
        $party = Party::whereCode($code)->first();
        $force = $this->option('force');
        if (!$party) {
            $this->output->error('Party not found');
            return self::FAILURE;
        }
        $party->fixPlaylist($force);
        $this->output->writeln("Recreated playlist for [Party:{$party->id}] {$party->name}");
        return self::SUCCESS;
    }
}

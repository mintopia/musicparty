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
    protected $signature = 'party:fixplaylist {code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix broken playlists';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = $this->argument('code');
        $party = Party::whereCode($code)->first();
        if (!$party) {
            $this->error("Unable to find a party with code {$code}");
            return self::FAILURE;
        }
        $this->output->writeln("{$party}: Fixing playlist");
        $party->fixPlaylist(true);
        $this->output->writeln("{$party}: Done");
        return self::SUCCESS;
    }
}

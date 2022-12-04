<?php

namespace App\Console\Commands;

use App\Models\Party;
use Illuminate\Console\Command;

class PartyFixPlaylists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:fixplaylists';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix all playlists';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Party::chunk(20, function ($parties) {
            foreach ($parties as $party) {
                $this->output->writeln("Fixing playlist for [Party:{$party->id}] {$party->name}");
                $party->fixPlaylist();
            }
        });
        return self::SUCCESS;
    }
}

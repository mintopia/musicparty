<?php

namespace App\Console\Commands;

use App\Jobs\EmptyContextPlaylist;
use App\Models\Party;
use Illuminate\Console\Command;

class PartyContextPlaylist extends Command
{
    /**
     * @var string
     */
    protected $signature = 'party:contextplaylist {code?}';

    /**
     * @var string
     */
    protected $description = 'Make sure the context playlist of every active party is still empty';

    public function handle(): int
    {
        $query = Party::whereActive(true)->whereNotNull('context_playlist_id');
        $code = $this->argument('code');
        if (is_string($code)) {
            $query->whereCode($code);
        }

        $query->each(fn (Party $party) => EmptyContextPlaylist::dispatch($party));

        return self::SUCCESS;
    }
}

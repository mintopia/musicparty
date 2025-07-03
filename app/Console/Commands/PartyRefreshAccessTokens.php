<?php

namespace App\Console\Commands;

use App\Models\SocialProvider;
use Illuminate\Console\Command;

class PartyRefreshAccessTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:refreshaccesstokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Spotify Access Tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $provider = SocialProvider::where('code', 'spotify')->first();
        if (!$provider) {
            $this->output->writeln('No spotify provider found');
            return self::SUCCESS;
        }
        $provider->accounts()->where('access_token_expires_at', '<', now()->addMinutes(4))->chunk(50, function ($chunk) {
            foreach ($chunk as $account) {
                $this->output->writeln("Refreshing Spotify access token for {$account->user}");
                $account->user->getSpotifyApi();
            }
        });
    }
}

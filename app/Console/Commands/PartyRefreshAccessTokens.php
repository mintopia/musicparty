<?php

namespace App\Console\Commands;

use App\Models\SocialProvider;
use App\Services\Spotify\SpotifyRelinkRequiredException;
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
    public function handle(): int
    {
        $provider = SocialProvider::where('code', 'spotify')->first();
        if (! $provider) {
            $this->output->writeln('No spotify provider found');

            return self::SUCCESS;
        }
        $provider->accounts()->whereNull('needs_relink_at')->where('access_token_expires_at', '<', now()->addMinutes(4))->chunk(50, function ($chunk) {
            foreach ($chunk as $account) {
                $this->output->writeln("Refreshing Spotify access token for {$account->user}");
                try {
                    $account->user->getSpotifyApi();
                } catch (SpotifyRelinkRequiredException) {
                    $this->output->writeln("Spotify needs re-linking for {$account->user}");
                }
            }
        });

        return self::SUCCESS;
    }
}

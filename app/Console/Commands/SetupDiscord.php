<?php

namespace App\Console\Commands;

use App\Models\SocialProvider;
use App\Services\SocialProviders\DiscordProvider;
use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class SetupDiscord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:discord';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure the Discord Social Provider';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $provider = SocialProvider::whereCode('discord')->first();
        if (!$provider) {
            $prov = new DiscordProvider();
            $provider = $prov->install();
        }

        $clientId = $provider->settings()->whereCode('client_id')->first();
        $secret = $provider->settings()->whereCode('client_secret')->first();

        $clientId->value = text(
            label: 'Discord Client ID',
            hint: 'This can be found in your Discord Developer OAuth2 settings',
            default: $clientId->value ?? ''
        );

        if (!$secret->value || confirm('Do you want to change the Client Secret?', false)) {
            $secret->value = password(
                label: 'Discord Client Secret'
            );
        }

        $provider->enabled = confirm(
            label: 'Do you want to enable the Discord provider?'
        );

        $provider->auth_enabled = confirm(
            label: 'Do you want to enable login with Discord?'
        );

        $provider->save();
        $clientId->save();
        $secret->save();

        \Laravel\Prompts\info('The provider has been updated');

        table(
            ['OAuth2 Redirect URLs'],
            [
                [route('login.return', 'discord')],
                [route('linkedaccounts.store', 'discord')],
            ],
        );
    }
}

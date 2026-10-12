<?php

namespace App\Console\Commands;

use App\Domain\Music\Jobs\RefreshExpiringHostTokens;
use App\Domain\Music\Providers\Spotify\HostAccountTokens;

class PartyRefreshAccessTokens extends PartyCommand
{
    protected $signature = 'party:refreshaccesstokens';

    protected $description = 'Refresh Host access tokens that are close to expiry';

    protected function perform(): int
    {
        (new RefreshExpiringHostTokens)->handle(app(HostAccountTokens::class));
        $this->info('Expiring Host access tokens refreshed.');

        return self::SUCCESS;
    }
}

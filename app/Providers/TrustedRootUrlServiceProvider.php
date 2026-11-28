<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class TrustedRootUrlServiceProvider extends ServiceProvider
{
    /**
     * Wayfinder reads the forced root off the URL generator, so forcing it
     * during generation would bake an absolute host into the frontend routes.
     */
    private const array SKIPPED_COMMANDS = ['wayfinder:generate'];

    public function boot(): void
    {
        if (! $this->app->isProduction() || $this->app->runningConsoleCommand(self::SKIPPED_COMMANDS)) {
            return;
        }

        $rootUrl = config('app.url');
        URL::forceRootUrl($rootUrl);
        URL::forceScheme(parse_url($rootUrl, PHP_URL_SCHEME) ?: 'https');
    }
}

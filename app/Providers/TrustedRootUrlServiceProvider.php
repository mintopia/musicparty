<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class TrustedRootUrlServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            $rootUrl = config('app.url');
            URL::forceRootUrl($rootUrl);
            URL::forceScheme(parse_url($rootUrl, PHP_URL_SCHEME) ?: 'https');
        }
    }
}

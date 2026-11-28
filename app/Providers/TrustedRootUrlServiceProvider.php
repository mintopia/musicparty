<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class TrustedRootUrlServiceProvider extends ServiceProvider
{
    /**
     * Wayfinder reads the forced root off the URL generator, so forcing it
     * during generation would bake an absolute host into the frontend routes.
     */
    private const array SKIPPED_COMMANDS = ['wayfinder:generate', 'package:discover'];

    private const array LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]', '0.0.0.0'];

    public function boot(): void
    {
        if (! $this->app->isProduction() || $this->app->runningConsoleCommand(self::SKIPPED_COMMANDS)) {
            return;
        }

        $rootUrl = (string) config('app.url');
        $parts = parse_url($rootUrl);
        $host = $parts['host'] ?? null;

        if (! is_string($host) || in_array(strtolower($host), self::LOCAL_HOSTS, true)) {
            throw new RuntimeException('APP_URL must be set to the public URL of this deployment (for example https://music.example.com) in production; it is empty or points at localhost. Only a single hostname is supported.');
        }

        $scheme = $parts['scheme'] ?? 'https';
        URL::forceRootUrl($rootUrl);
        URL::forceScheme($scheme);

        if ((bool) config('session.secure') !== ($scheme === 'https')) {
            Log::warning('SESSION_SECURE_COOKIE does not match the APP_URL scheme; logins may fail or the cookie may be sent insecurely.', [
                'app_url' => $rootUrl,
                'session_secure' => (bool) config('session.secure'),
            ]);
        }
    }
}

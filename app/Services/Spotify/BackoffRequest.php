<?php

namespace App\Services\Spotify;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SpotifyWebAPI\Request;
use SpotifyWebAPI\SpotifyWebAPIException;

/**
 * Spotify HTTP transport that records a 429 backoff per client application
 * (e.g. "spotify" or "spotifysearch") and short-circuits calls while it lasts.
 */
class BackoffRequest extends Request
{
    private const int DEFAULT_RETRY_AFTER = 1;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(protected string $application, array $options = [])
    {
        parent::__construct($options);
    }

    public static function backoffRemaining(string $application): int
    {
        $until = Cache::get(self::cacheKey($application));

        return is_int($until) ? max(0, $until - time()) : 0;
    }

    /**
     * @param  string|array<string, mixed>|object  $parameters
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function send(string $method, string $url, string|array|object $parameters = [], array $headers = []): array
    {
        $remaining = self::backoffRemaining($this->application);
        if ($remaining > 0) {
            throw new SpotifyBackoffException($remaining);
        }

        try {
            return $this->transport($method, $url, $parameters, $headers);
        } catch (SpotifyWebAPIException $e) {
            if ($e->isRateLimited()) {
                Log::critical('Spotify Rate Limit Hit');
                $this->startBackoff($this->retryAfterSeconds());
            }

            throw $e;
        }
    }

    /**
     * @param  string|array<string, mixed>|object  $parameters
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    protected function transport(string $method, string $url, string|array|object $parameters, array $headers): array
    {
        return parent::send($method, $url, $parameters, $headers);
    }

    protected function retryAfterSeconds(): int
    {
        $header = $this->getLastResponse()['headers']['retry-after'] ?? null;

        return is_numeric($header) ? max(1, (int) $header) : self::DEFAULT_RETRY_AFTER;
    }

    protected function startBackoff(int $seconds): void
    {
        Cache::put(self::cacheKey($this->application), time() + $seconds, $seconds);
    }

    private static function cacheKey(string $application): string
    {
        return "spotify:backoff:{$application}";
    }
}

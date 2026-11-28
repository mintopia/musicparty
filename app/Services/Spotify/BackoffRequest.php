<?php

namespace App\Services\Spotify;

use Illuminate\Support\Facades\Cache;
use SpotifyWebAPI\Request;
use SpotifyWebAPI\SpotifyWebAPIException;

/**
 * Spotify HTTP transport that records a 429 backoff per client application
 * (e.g. "spotify" or "spotifysearch") and short-circuits calls while it lasts.
 */
class BackoffRequest extends Request
{
    private const int DEFAULT_RETRY_AFTER = 1;

    private const int CURL_TIMEOUT_ERRNO = 28;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(protected string $application, array $options = [])
    {
        parent::__construct($options);
    }

    public static function forApplication(string $application): self
    {
        return new self($application, ['curl_options' => [
            CURLOPT_TIMEOUT => (int) config('musicparty.spotify_timeout_seconds'),
            CURLOPT_CONNECTTIMEOUT => (int) config('musicparty.spotify_connect_timeout_seconds'),
        ]]);
    }

    public static function transportErrno(SpotifyWebAPIException $e): ?int
    {
        return preg_match('/^cURL transport error: (\d+)/', $e->getMessage(), $matches) === 1 ? (int) $matches[1] : null;
    }

    public static function isTimeout(SpotifyWebAPIException $e): bool
    {
        return self::transportErrno($e) === self::CURL_TIMEOUT_ERRNO;
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
            return $this->transportWithRetry($method, $url, $parameters, $headers);
        } catch (SpotifyWebAPIException $e) {
            if ($e->isRateLimited()) {
                $this->startBackoff($this->retryAfterSeconds());
            }

            throw $e;
        }
    }

    /**
     * Retries a GET once when the connection failed outright; a timeout is not retried because it would double the wait.
     *
     * @param  string|array<string, mixed>|object  $parameters
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function transportWithRetry(string $method, string $url, string|array|object $parameters, array $headers): array
    {
        try {
            return $this->transport($method, $url, $parameters, $headers);
        } catch (SpotifyWebAPIException $e) {
            if ($method !== 'GET' || self::transportErrno($e) === null || self::isTimeout($e)) {
                throw $e;
            }

            return $this->transport($method, $url, $parameters, $headers);
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

    protected function handleResponseError(string $body, int $status): void
    {
        try {
            parent::handleResponseError($body, $status);
        } catch (SpotifyWebAPIException $e) {
            $error = json_decode($body)->error ?? null;
            if (is_string($error)) {
                $e->setReason($error);
            }

            throw $e;
        }
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

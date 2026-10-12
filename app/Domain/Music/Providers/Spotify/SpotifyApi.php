<?php

namespace App\Domain\Music\Providers\Spotify;

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\ProviderCredentials;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Shared Spotify Web API plumbing: configuration, rate-limit backoff, response guarding and Host-authorised requests.
 */
class SpotifyApi
{
    public const string ID = 'spotify';

    public const string URL = 'https://api.spotify.com/v1';

    public const string SEARCH_ID = 'spotifysearch';

    private const string BACKOFF_CACHE_PREFIX = 'music.spotify.backoff.';

    private const int DEFAULT_RETRY_AFTER_SECONDS = 5;

    private const int HTTP_TIMEOUT_SECONDS = 5;

    public function __construct(private readonly HostAccountTokens $hostTokens) {}

    /**
     * @param  Closure(PendingRequest): Response  $send
     *
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function userRequest(Closure $send, string $hostAccountId): Response
    {
        $account = $this->hostAccount($hostAccountId);
        $this->assertNotBackingOff();

        try {
            $response = $send(Http::withToken($this->hostTokens->accessToken($account))->acceptJson()->timeout(self::HTTP_TIMEOUT_SECONDS));

            if ($response->status() === 401) {
                $response = $send(Http::withToken($this->hostTokens->refreshAfterRejection($account))->acceptJson()->timeout(self::HTTP_TIMEOUT_SECONDS));
            }

            return $response;
        } catch (ConnectionException $failure) {
            throw new ProviderTemporaryFailure('Spotify could not be reached.', outcomeUnknown: ! $this->neverConnected($failure));
        }
    }

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function guard(Response $response, ?string $clientId = null): void
    {
        $status = $response->status();

        if ($status === 429) {
            throw $this->startBackoff($response, $clientId);
        }

        if ($status === 401 || $status === 403) {
            throw ProviderUnavailableException::forProvider(self::ID);
        }

        if ($status >= 400) {
            throw new ProviderTemporaryFailure("Spotify responded with status {$status}.", outcomeUnknown: $status >= 500);
        }
    }

    public function startBackoff(Response $response, ?string $clientId = null): ProviderTemporaryFailure
    {
        $header = $response->header('Retry-After');
        $retryAfter = is_numeric($header) ? max(1, (int) $header) : self::DEFAULT_RETRY_AFTER_SECONDS;

        Cache::put($this->backoffKey($clientId), now()->getTimestamp() + $retryAfter, $retryAfter);

        return new ProviderTemporaryFailure('Spotify is rate limiting requests.', $retryAfter);
    }

    /**
     * @throws ProviderTemporaryFailure
     */
    public function assertNotBackingOff(?string $clientId = null): void
    {
        $until = Cache::get($this->backoffKey($clientId));

        if (! is_int($until)) {
            return;
        }

        $remaining = $until - now()->getTimestamp();

        if ($remaining > 0) {
            throw new ProviderTemporaryFailure('Spotify is rate limiting requests.', $remaining);
        }
    }

    /**
     * @throws ProviderUnavailableException
     */
    public function assertConfigured(): void
    {
        if ($this->clientId() === null || $this->clientSecret() === null) {
            throw ProviderUnavailableException::forProvider(self::ID);
        }
    }

    public function clientId(string $application = self::ID): ?string
    {
        return app(ProviderCredentials::class)->get($application, 'client_id');
    }

    public function clientSecret(string $application = self::ID): ?string
    {
        return app(ProviderCredentials::class)->get($application, 'client_secret');
    }

    public function market(): ?string
    {
        $value = config('services.spotify.market');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function neverConnected(ConnectionException $failure): bool
    {
        return preg_match('/cURL error (6|7)\b|refused|could not resolve/i', $failure->getMessage()) === 1;
    }

    private function hostAccount(string $hostAccountId): LinkedAccount
    {
        $account = ctype_digit($hostAccountId) ? LinkedAccount::query()->find((int) $hostAccountId) : null;

        if ($account === null) {
            throw ProviderUnavailableException::forProvider(self::ID);
        }

        $this->assertConfigured();

        return $account;
    }

    private function backoffKey(?string $clientId): string
    {
        return self::BACKOFF_CACHE_PREFIX.hash('sha256', $clientId ?? $this->clientId() ?? '');
    }
}

<?php

namespace App\Domain\Music\Providers\Spotify;

use App\Domain\Identity\Models\LinkedAccount;
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

    private const string BACKOFF_CACHE_KEY = 'music.spotify.backoff-until';

    private const int DEFAULT_RETRY_AFTER_SECONDS = 30;

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
            $response = $send(Http::withToken($this->hostTokens->accessToken($account))->acceptJson());

            if ($response->status() === 401) {
                $response = $send(Http::withToken($this->hostTokens->refreshAfterRejection($account))->acceptJson());
            }

            return $response;
        } catch (ConnectionException) {
            throw new ProviderTemporaryFailure('Spotify could not be reached.');
        }
    }

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function guard(Response $response): void
    {
        $status = $response->status();

        if ($status === 429) {
            throw $this->startBackoff($response);
        }

        if ($status === 401 || $status === 403) {
            throw ProviderUnavailableException::forProvider(self::ID);
        }

        if ($status >= 400) {
            throw new ProviderTemporaryFailure("Spotify responded with status {$status}.");
        }
    }

    public function startBackoff(Response $response): ProviderTemporaryFailure
    {
        $header = $response->header('Retry-After');
        $retryAfter = is_numeric($header) ? max(1, (int) $header) : self::DEFAULT_RETRY_AFTER_SECONDS;

        Cache::put(self::BACKOFF_CACHE_KEY, now()->getTimestamp() + $retryAfter, $retryAfter);

        return new ProviderTemporaryFailure('Spotify is rate limiting requests.', $retryAfter);
    }

    /**
     * @throws ProviderTemporaryFailure
     */
    public function assertNotBackingOff(): void
    {
        $until = Cache::get(self::BACKOFF_CACHE_KEY);

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

    public function clientId(): ?string
    {
        return $this->configured('client_id');
    }

    public function clientSecret(): ?string
    {
        return $this->configured('client_secret');
    }

    public function market(): ?string
    {
        return $this->configured('market');
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

    private function configured(string $key): ?string
    {
        $value = config("services.spotify.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }
}

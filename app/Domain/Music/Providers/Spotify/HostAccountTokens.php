<?php

namespace App\Domain\Music\Providers\Spotify;

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Music\Exceptions\HostAccountNeedsRelink;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HostAccountTokens
{
    private const string TOKEN_URL = 'https://accounts.spotify.com/api/token';

    private const int REFRESH_WINDOW_SECONDS = 300;

    private const int LOCK_SECONDS = 30;

    private const int LOCK_WAIT_SECONDS = 10;

    /**
     * @throws HostAccountNeedsRelink
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function accessToken(LinkedAccount $account): string
    {
        $this->assertLinked($account);

        if ($this->isFresh($account)) {
            return (string) $account->access_token;
        }

        try {
            return Cache::lock("music.spotify.refresh.{$account->getKey()}", self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn (): string => $this->refreshLocked($account));
        } catch (LockTimeoutException) {
            throw new ProviderTemporaryFailure('Spotify token refresh is already in progress.', 1);
        }
    }

    /**
     * @throws HostAccountNeedsRelink
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function refreshAfterRejection(LinkedAccount $account): string
    {
        $account->forceFill(['access_token_expires_at' => now()->subSecond()])->save();

        return $this->accessToken($account);
    }

    private function refreshLocked(LinkedAccount $account): string
    {
        $account->refresh();
        $this->assertLinked($account);

        if ($this->isFresh($account)) {
            return (string) $account->access_token;
        }

        $response = $this->requestRefresh($account);

        if ($response->status() === 429 || $response->serverError()) {
            $header = $response->header('Retry-After');

            throw new ProviderTemporaryFailure('Spotify token endpoint is unavailable.', is_numeric($header) ? max(1, (int) $header) : null);
        }

        if ($response->failed()) {
            if ($response->json('error') === 'invalid_grant') {
                $this->rejected($account);
            }

            throw ProviderUnavailableException::rejectedRefresh('spotify');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new ProviderTemporaryFailure('Spotify token endpoint returned an unusable response.');
        }

        $rotated = $response->json('refresh_token');
        $account->access_token = $token;
        $account->access_token_expires_at = now()->addSeconds((int) $response->json('expires_in', 3600));

        if (is_string($rotated) && $rotated !== '') {
            $account->refresh_token = $rotated;
        }

        $account->save();

        return $token;
    }

    private function requestRefresh(LinkedAccount $account): Response
    {
        $clientId = config('services.spotify.client_id');
        $clientSecret = config('services.spotify.client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw ProviderUnavailableException::forProvider('spotify');
        }

        try {
            return Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => (string) $account->refresh_token,
                ]);
        } catch (ConnectionException) {
            throw new ProviderTemporaryFailure('Spotify token endpoint could not be reached.');
        }
    }

    private function rejected(LinkedAccount $account): never
    {
        $account->needs_relink = true;
        $account->save();

        throw HostAccountNeedsRelink::forAccount($account->getKey());
    }

    private function assertLinked(LinkedAccount $account): void
    {
        if ($account->needs_relink || ! is_string($account->refresh_token) || $account->refresh_token === '') {
            throw HostAccountNeedsRelink::forAccount($account->getKey());
        }
    }

    private function isFresh(LinkedAccount $account): bool
    {
        $expiresAt = $account->access_token_expires_at;

        return is_string($account->access_token)
            && $account->access_token !== ''
            && $expiresAt !== null
            && $expiresAt->getTimestamp() - now()->getTimestamp() > self::REFRESH_WINDOW_SECONDS;
    }
}

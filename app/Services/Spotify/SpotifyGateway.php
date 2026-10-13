<?php

namespace App\Services\Spotify;

use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Support\SpotifyPayload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\SpotifyWebAPIException;
use stdClass;

class SpotifyGateway
{
    /** @var array<int, Session> */
    protected array $sessions = [];

    /** @var array<int, SpotifyWebAPI> */
    protected array $clients = [];

    public function client(User $user): ?SpotifyWebAPI
    {
        $account = $this->account($user, 'spotify');
        if ($account === null) {
            return null;
        }

        if ($account->needsRelink()) {
            throw new SpotifyRelinkRequiredException;
        }

        [$session, $api] = $this->build($account, 'spotify', $user);

        if ($account->access_token_expires_at < now()->addMinutes(5)) {
            Log::debug("{$user}: Refreshing expiring access token");
            $this->refreshToken($account, $session);
            SpotifyAccessTokenUpdatedEvent::dispatch($user, $account->access_token ?? '');
        }

        return $api;
    }

    public function requireClient(User $user): SpotifyWebAPI
    {
        return $this->client($user) ?? throw new RuntimeException("{$user}: No linked Spotify account");
    }

    public function accessToken(User $user): ?string
    {
        if ($this->client($user) === null) {
            return null;
        }
        $account = $this->account($user, 'spotify');

        return $account === null ? null : ($this->sessions[$account->id] ?? null)?->getAccessToken();
    }

    public function searchClient(User $user): SpotifyWebAPI
    {
        $account = $this->account($user, 'spotifysearch');
        if ($account === null || $account->needsRelink()) {
            Log::warning("{$user}: No Spotify Search account, using control account");

            return $this->requireClient($user);
        }

        [$session, $api] = $this->build($account, 'spotifysearch', $user);

        if ($account->access_token_expires_at < now()->addMinutes(5)) {
            Log::debug("{$user}: Refreshing expiring Search API access token");
            $this->refreshToken($account, $session);
        }

        return $api;
    }

    public function findTrack(User $user, string $id): ?stdClass
    {
        try {
            return SpotifyPayload::object($this->requireClient($user)->getTrack($id));
        } catch (SpotifyWebAPIException) {
            return null;
        }
    }

    /**
     * Refreshes the Spotify access token under a per-account lock, re-reading the stored
     * token inside it so concurrent callers reuse a refresh that already happened.
     *
     * @throws SpotifyRelinkRequiredException when Spotify rejects the refresh token
     */
    public function refreshToken(LinkedAccount $account, Session $session): void
    {
        Cache::lock("linked-account-refresh:{$account->id}", 30)->block(10, function () use ($account, $session): void {
            $account->refresh();

            if ($account->needsRelink()) {
                throw new SpotifyRelinkRequiredException;
            }

            if ($account->access_token_expires_at !== null && $account->access_token_expires_at >= now()->addMinutes(5)) {
                $session->setAccessToken($account->access_token ?? '');

                return;
            }

            try {
                $refreshed = $session->refreshAccessToken($account->refresh_token ?? '');
            } catch (SpotifyWebAPIException $e) {
                if ($this->isRevokedRefresh($e)) {
                    Log::warning("Linked account {$account->id}: Spotify refresh revoked, marking for re-link");
                    $account->needs_relink_at = now();
                    $account->save();

                    throw new SpotifyRelinkRequiredException;
                }

                throw $e;
            }

            if (! $refreshed) {
                throw new SpotifyWebAPIException('Spotify token refresh returned no access token');
            }

            $account->access_token = $session->getAccessToken();
            $account->access_token_expires_at = new Carbon($session->getTokenExpiration());
            $rotated = $session->getRefreshToken();
            if ($rotated !== '' && $rotated !== $account->refresh_token) {
                $account->refresh_token = $rotated;
            }
            $account->save();
        });
    }

    private function isRevokedRefresh(SpotifyWebAPIException $e): bool
    {
        return in_array($e->getCode(), [400, 401], true) || str_contains($e->getMessage(), 'invalid_grant');
    }

    protected function account(User $user, string $providerCode): ?LinkedAccount
    {
        $account = $user->accounts()->with('provider')->whereHas('provider', function ($query) use ($providerCode) {
            $query->whereCode($providerCode);
        })->first();

        return $account?->provider === null ? null : $account;
    }

    /** @return array{0: Session, 1: SpotifyWebAPI} */
    protected function build(LinkedAccount $account, string $backoffApplication, User $user): array
    {
        $session = $this->sessions[$account->id] ??= $this->makeSession($account);

        if (! isset($this->clients[$account->id])) {
            Log::debug("{$user}: Creating new {$backoffApplication} API connection");
            $this->clients[$account->id] = app(SpotifyWebAPI::class, [
                'options' => [],
                'session' => $session,
                'request' => new BackoffRequest($backoffApplication),
            ]);
        }

        return [$session, $this->clients[$account->id]];
    }

    protected function makeSession(LinkedAccount $account): Session
    {
        $provider = $account->provider;
        $session = new Session((string) $provider?->getSetting('client_id'), (string) $provider?->getSetting('client_secret'));
        $session->setAccessToken($account->access_token ?? '');

        return $session;
    }
}

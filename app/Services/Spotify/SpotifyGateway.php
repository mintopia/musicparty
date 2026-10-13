<?php

namespace App\Services\Spotify;

use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;

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
            $account->refreshSpotifyToken($session);
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
            $account->refreshSpotifyToken($session);
        }

        return $api;
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

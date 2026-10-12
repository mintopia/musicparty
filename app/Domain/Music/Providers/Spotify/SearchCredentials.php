<?php

namespace App\Domain\Music\Providers\Spotify;

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesHost;

class SearchCredentials
{
    public function __construct(private readonly SpotifyApi $api, private readonly AuthorisesHost $hosts) {}

    public function searchProviderEnabled(): bool
    {
        return SocialProvider::query()->where('code', SpotifyApi::SEARCH_ID)->where('enabled', true)->exists()
            && $this->api->clientId(SpotifyApi::SEARCH_ID) !== null
            && $this->api->clientSecret(SpotifyApi::SEARCH_ID) !== null;
    }

    public function resolve(?User $host): SearchSource
    {
        if ($host !== null) {
            $search = $this->searchProviderEnabled() ? $this->usable($this->hosts->linkedAccountFor($host, SpotifyApi::SEARCH_ID)) : null;

            if ($search !== null) {
                return new SearchSource($this->api->clientId(SpotifyApi::SEARCH_ID), $search);
            }

            $control = $this->api->clientId() !== null ? $this->usable($this->hosts->linkedAccountFor($host, SpotifyApi::ID)) : null;

            if ($control !== null) {
                return new SearchSource($this->api->clientId(), $control);
            }
        }

        return new SearchSource($this->api->clientId());
    }

    private function usable(?LinkedAccount $account): ?LinkedAccount
    {
        if ($account === null || $account->needs_relink) {
            return null;
        }

        return ($account->refresh_token ?? '') !== '' ? $account : null;
    }
}

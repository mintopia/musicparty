<?php

namespace App\Domain\Music\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Providers\Spotify\SearchCredentials;
use App\Domain\Music\Providers\Spotify\SpotifyApi;

readonly class SpotifySearchLinkStatus
{
    public function __construct(private AuthorisesHost $authorisesHost, private SearchCredentials $credentials) {}

    public function enabled(): bool
    {
        return $this->credentials->searchProviderEnabled();
    }

    /**
     * @return array{linked: bool, name: string|null, needs_relink: bool}
     */
    public function __invoke(User $user): array
    {
        $account = $this->authorisesHost->linkedAccountFor($user, SpotifyApi::SEARCH_ID);
        $linked = $account !== null && ($account->access_token ?? $account->refresh_token ?? '') !== '';

        return [
            'linked' => $linked,
            'name' => $linked ? $account->name : null,
            'needs_relink' => $linked && $account->needs_relink,
        ];
    }

    public function needsRelinkWarning(User $user): bool
    {
        return $this->enabled() && $this($user)['needs_relink'];
    }
}

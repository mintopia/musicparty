<?php

namespace App\Domain\Music\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Providers\Spotify\SpotifyScopes;

readonly class SpotifyControlLinkStatus
{
    public function __construct(private AuthorisesHost $authorisesHost) {}

    /**
     * @return array{linked: bool, name: string|null, needs_relink: bool}
     */
    public function __invoke(User $user): array
    {
        $account = $this->authorisesHost->linkedAccountFor($user, 'spotify');
        $linked = $account !== null && ($account->access_token ?? $account->refresh_token ?? '') !== '';

        return [
            'linked' => $linked,
            'name' => $linked ? $account->name : null,
            'needs_relink' => $linked && ($account->needs_relink || SpotifyScopes::lacksPlayback($account->scopes)),
        ];
    }

    public function isUsable(User $user): bool
    {
        $status = $this($user);

        return $status['linked'] && ! $status['needs_relink'];
    }
}

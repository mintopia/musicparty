<?php

namespace App\Domain\Music\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Data\PlaylistData;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Party\Models\Party;

class ListHostPlaylists
{
    public function __construct(private readonly AuthorisesHost $authorisesHost) {}

    /**
     * @return list<PlaylistData>
     *
     * @throws NotHostException
     * @throws ProviderTemporaryFailure
     */
    public function __invoke(User $host, Party $party, MusicProvider $provider): array
    {
        $this->authorisesHost->__invoke($host, $party);

        $account = $this->authorisesHost->linkedAccountFor($host, $provider->id());

        if ($account === null) {
            return [];
        }

        return $provider->playlists((string) $account->id);
    }
}

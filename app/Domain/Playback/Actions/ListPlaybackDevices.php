<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Actions\SpotifyControlLinkStatus;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\Data\PlaybackDevice;
use App\Domain\Playback\Exceptions\PlaybackDevicesUnavailableException;

readonly class ListPlaybackDevices
{
    public function __construct(
        private AuthorisesHost $authorisesHost,
        private SpotifyControlLinkStatus $linkStatus,
        private PlaybackClient $client,
    ) {}

    /**
     * @return list<PlaybackDevice>
     *
     * @throws NotHostException
     * @throws PlaybackDevicesUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function __invoke(User $host, Party $party): array
    {
        ($this->authorisesHost)($host, $party);

        $account = $this->authorisesHost->linkedAccountFor($host, 'spotify');

        if ($account === null || ! $this->linkStatus->isUsable($host)) {
            throw new PlaybackDevicesUnavailableException;
        }

        try {
            return $this->client->devices((string) $account->getKey());
        } catch (ProviderUnavailableException) {
            throw new PlaybackDevicesUnavailableException;
        }
    }
}

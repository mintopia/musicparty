<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Models\Party;
use App\Models\User;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\Playlist;
use App\Services\Music\MusicProviderFactory;

/**
 * The playlists and devices a Party's chosen Music Provider offers its owner.
 */
class PartyProviderOptions
{
    public function __construct(protected MusicProviderFactory $factory) {}

    /**
     * A not-yet-saved Party, so a provider can be resolved before the Party exists.
     */
    public function draft(User $owner, ?string $providerId): Party
    {
        $party = new Party;
        $party->setRelation('user', $owner);
        $party->setAttribute('provider_id', $providerId);

        return $party;
    }

    /**
     * @return list<Playlist>
     */
    public function playlists(Party $party): array
    {
        return $this->safely($party, fn (Party $party): array => $this->factory->forParty($party, Purpose::Control)->listPlaylists());
    }

    /**
     * @return list<Device>
     */
    public function devices(Party $party): array
    {
        return $this->safely($party, fn (Party $party): array => $this->factory->forParty($party, Purpose::Control)->devices());
    }

    /**
     * @template T
     *
     * @param  callable(Party): list<T>  $fetch
     * @return list<T>
     */
    protected function safely(Party $party, callable $fetch): array
    {
        $id = $party->getAttribute('provider_id');
        if (is_string($id) && $id !== '' && ! in_array($id, $this->factory->ids(), true)) {
            return [];
        }

        try {
            return $fetch($party);
        } catch (ProviderRelinkRequiredException|ProviderBackoffException) {
            return [];
        }
    }
}

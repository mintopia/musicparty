<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\Data\SearchHit;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\TrackRequest;

class SearchPartyProvider
{
    public const LIMIT = 20;

    public function __construct(private readonly PairingCatalogue $catalogue) {}

    /**
     * @return list<SearchHit>
     */
    public function __invoke(Party $party, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $page = $this->catalogue->provider($party->music_provider)->search($query, self::LIMIT, 0);
        } catch (ProviderTemporaryFailure|ProviderUnavailableException) {
            throw RequestRefusedException::providerUnavailable();
        }

        $queued = array_flip(TrackRequest::query()
            ->where('party_id', $party->id)
            ->where('status', RequestStatus::Queued)
            ->pluck('provider_track_id')
            ->all());

        return array_map(
            fn ($track): SearchHit => new SearchHit($track, isset($queued[$track->providerTrackId])),
            $page->items,
        );
    }
}

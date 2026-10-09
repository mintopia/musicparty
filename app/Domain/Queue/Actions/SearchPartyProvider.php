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
use App\Models\User;

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

        $queued = TrackRequest::query()
            ->where('party_id', $party->id)
            ->where('status', RequestStatus::Queued)
            ->with('requester.user')
            ->withSum('votes as score', 'value')
            ->oldest('id')
            ->get()
            ->unique('provider_track_id')
            ->keyBy('provider_track_id');

        return array_map(
            function ($track) use ($queued): SearchHit {
                $request = $queued->get($track->providerTrackId);
                $requester = $request?->requester?->user;

                return new SearchHit(
                    $track,
                    $request !== null,
                    $requester instanceof User ? $requester->nickname : null,
                    (int) $request?->score,
                );
            },
            $page->items,
        );
    }
}

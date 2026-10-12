<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Queue\Data\SearchHit;
use App\Domain\Queue\Data\SearchResults;
use App\Domain\Queue\Exceptions\ProviderRateLimitedException;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;

class SearchPartyProvider
{
    public const LIMIT = 20;

    public function __construct(private readonly PairingCatalogue $catalogue) {}

    public function __invoke(Party $party, string $query, int $page = 1): SearchResults
    {
        $query = trim($query);
        if ($query === '') {
            return new SearchResults([], false);
        }

        try {
            $provider = $this->catalogue->provider($party->music_provider);
            $results = $provider->search($query, self::LIMIT, (max(1, $page) - 1) * self::LIMIT, $party->user);
        } catch (ProviderTemporaryFailure $failure) {
            throw $failure->retryAfterSeconds === null
                ? RequestRefusedException::providerUnavailable()
                : new ProviderRateLimitedException($failure->retryAfterSeconds);
        } catch (ProviderUnavailableException) {
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

        $hits = array_map(
            function ($track) use ($queued, $provider): SearchHit {
                $request = $queued->get($track->providerTrackId);
                $requester = $request?->requester?->user;

                return new SearchHit(
                    $track,
                    $request !== null,
                    $requester instanceof User ? $requester->nickname : null,
                    (int) $request?->score,
                    $provider->trackUrl($track->providerTrackId),
                );
            },
            $results->items,
        );

        return new SearchResults($hits, $results->hasMore());
    }
}

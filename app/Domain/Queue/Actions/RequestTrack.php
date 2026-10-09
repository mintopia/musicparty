<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Data\RequestOutcome;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\QueueUpdatedEvent;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RequestTrack
{
    public function __construct(private readonly PairingCatalogue $catalogue) {}

    public function __invoke(Party $party, PartyMember $member, string $providerTrackId): RequestOutcome
    {
        if ($member->banned) {
            throw RequestRefusedException::banned();
        }

        if ($party->state !== PartyState::Live) {
            throw RequestRefusedException::partyNotLive();
        }

        $track = $this->fetchTrack($party->music_provider, $providerTrackId);

        $outcome = DB::transaction(function () use ($member, $party, $track): RequestOutcome {
            $existing = TrackRequest::query()
                ->where('party_id', $party->id)
                ->where('status', RequestStatus::Queued)
                ->where('provider_track_id', $track->providerTrackId)
                ->oldest('id')
                ->first();

            if ($existing !== null) {
                $this->castUpvote($existing, $member);

                return new RequestOutcome($existing, false);
            }

            $request = TrackRequest::query()->create([
                'party_id' => $party->id,
                'party_member_id' => $member->id,
                'provider_track_id' => $track->providerTrackId,
                'title' => $track->name,
                'artists' => array_map(fn ($artist): string => $artist->name, $track->artists),
                'album' => $track->album->name,
                'artwork_url' => $track->coverArtUrls[0] ?? null,
                'duration_ms' => $track->durationMs,
                'explicit' => $track->explicit,
                'status' => RequestStatus::Queued,
            ]);
            $this->castUpvote($request, $member);

            return new RequestOutcome($request, true);
        });

        QueueUpdatedEvent::dispatch($party->code);

        return $outcome;
    }

    private function fetchTrack(string $providerId, string $providerTrackId): TrackData
    {
        try {
            $track = $this->catalogue->provider($providerId)->getTrack($providerTrackId);
        } catch (ProviderTemporaryFailure|ProviderUnavailableException) {
            throw RequestRefusedException::providerUnavailable();
        }

        if ($track === null || ! $track->playableInMarket) {
            throw RequestRefusedException::unknownTrack();
        }

        return $track;
    }

    private function castUpvote(TrackRequest $request, PartyMember $member): void
    {
        try {
            RequestVote::query()->firstOrCreate(
                ['track_request_id' => $request->id, 'party_member_id' => $member->id],
                ['value' => 1],
            );
        } catch (UniqueConstraintViolationException) {
            return;
        }
    }
}

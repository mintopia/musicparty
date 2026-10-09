<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyRole;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Blocklist;
use App\Domain\Queue\Data\RequestOutcome;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\PendingRequestAddedEvent;
use App\Events\Party\QueueUpdatedEvent;
use App\Events\Party\RequestDecidedEvent;
use App\Events\Party\RequestRejectedEvent;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RequestTrack
{
    public function __construct(private readonly PairingCatalogue $catalogue, private readonly Blocklist $blocklist) {}

    public function __invoke(Party $party, PartyMember $member, string $providerTrackId): RequestOutcome
    {
        if ($member->banned) {
            throw RequestRefusedException::banned();
        }

        if ($party->state !== PartyState::Live) {
            throw RequestRefusedException::partyNotLive();
        }

        try {
            if (! $party->allow_requests) {
                throw RequestRefusedException::requestsDisabled();
            }

            $track = $this->fetchTrack($party->music_provider, $providerTrackId);

            $outcome = DB::transaction(fn (): RequestOutcome => $this->place($party, $member, $track));
        } catch (RequestRefusedException $refusal) {
            if ($refusal->status() === RequestRefusedException::RULE_VIOLATION || $refusal->status() === RequestRefusedException::CONFLICT) {
                RequestRejectedEvent::dispatch($party->code, $member->id, $providerTrackId, $refusal->getMessage());
            }

            throw $refusal;
        }

        $this->announce($party, $member, $outcome);

        return $outcome;
    }

    private function announce(Party $party, PartyMember $member, RequestOutcome $outcome): void
    {
        $request = $outcome->request;

        if ($request->status !== RequestStatus::Pending) {
            QueueUpdatedEvent::dispatch($party->code);

            return;
        }

        if ($outcome->created) {
            PendingRequestAddedEvent::dispatch($party->code, $request->id, $request->title, $request->artists, $member->id);
            RequestDecidedEvent::dispatch($party->code, $member->id, $request->id, RequestStatus::Pending->value, null);
        }
    }

    /**
     * A duplicate of an active Request only adds an upvote, so it bypasses the rules that gate a new Request.
     */
    private function place(Party $party, PartyMember $member, TrackData $track): RequestOutcome
    {
        Party::query()->whereKey($party->id)->lockForUpdate()->first();

        $existing = $this->matchingQuery($party, $track)
            ->whereIn('status', [RequestStatus::Pending, RequestStatus::Queued])
            ->oldest('id')
            ->first();

        if ($existing !== null) {
            return new RequestOutcome($existing, false, $this->castUpvote($existing, $member));
        }

        $this->enforceRules($party, $member, $track);

        $request = TrackRequest::query()->create([
            'party_id' => $party->id,
            'party_member_id' => $member->id,
            'provider_track_id' => $track->providerTrackId,
            'title' => $track->name,
            'artists' => array_map(fn ($artist): string => $artist->name, $track->artists),
            'album' => $track->album->name,
            'artwork_url' => $track->coverArtUrls[0] ?? null,
            'isrc' => $track->isrc,
            'duration_ms' => $track->durationMs,
            'explicit' => $track->explicit,
            'status' => $this->holds($party, $member) ? RequestStatus::Pending : RequestStatus::Queued,
        ]);
        $this->castUpvote($request, $member);

        return new RequestOutcome($request, true, true);
    }

    private function holds(Party $party, PartyMember $member): bool
    {
        return $party->hold_requests && ! in_array($member->role, [PartyRole::Host, PartyRole::Moderator], true);
    }

    /**
     * @return Builder<TrackRequest>
     */
    private function matchingQuery(Party $party, TrackData $track): Builder
    {
        return TrackRequest::query()
            ->where('party_id', $party->id)
            ->where(function (Builder $query) use ($track): void {
                $query->where('provider_track_id', $track->providerTrackId);
                if ($track->isrc !== null) {
                    $query->orWhere('isrc', $track->isrc);
                }
            });
    }

    private function enforceRules(Party $party, PartyMember $member, TrackData $track): void
    {
        $exempt = in_array($member->role, [PartyRole::Host, PartyRole::Vip], true);

        if (! $exempt && $party->max_requests !== null) {
            $active = TrackRequest::query()
                ->where('party_id', $party->id)
                ->where('party_member_id', $member->id)
                ->whereIn('status', [RequestStatus::Pending, RequestStatus::Queued, RequestStatus::UpNext])
                ->count();

            if ($active >= $party->max_requests) {
                throw RequestRefusedException::requestLimitReached($party->max_requests);
            }
        }

        $seconds = $track->durationMs / 1000;

        if ($party->min_song_length && $seconds < $party->min_song_length) {
            throw RequestRefusedException::trackTooShort($party->min_song_length);
        }

        if ($party->max_song_length && $seconds > $party->max_song_length) {
            throw RequestRefusedException::trackTooLong($party->max_song_length);
        }

        if (! $party->explicit && $track->explicit) {
            throw RequestRefusedException::explicitNotAllowed();
        }

        if ($this->blocklist->firstMatch($party, $track) !== null) {
            throw RequestRefusedException::blocklisted();
        }

        if ($party->no_repeat_interval) {
            $played = $this->matchingQuery($party, $track)
                ->where('status', RequestStatus::Played)
                ->where('updated_at', '>=', now()->subSeconds($party->no_repeat_interval))
                ->latest('updated_at')
                ->first();

            if ($played !== null) {
                throw RequestRefusedException::playedRecently($played->updated_at);
            }
        }

        if ($this->matchingQuery($party, $track)->where('status', RequestStatus::UpNext)->exists()) {
            throw RequestRefusedException::alreadyUpNext();
        }
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

    /**
     * Returns true only when this call recorded a new vote; a duplicate or a lost insert race returns false.
     */
    private function castUpvote(TrackRequest $request, PartyMember $member): bool
    {
        try {
            return RequestVote::query()->firstOrCreate(
                ['track_request_id' => $request->id, 'party_member_id' => $member->id],
                ['value' => 1],
            )->wasRecentlyCreated;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}

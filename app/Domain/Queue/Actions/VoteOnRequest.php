<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Events\VoteCast;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Exceptions\VoteRefusedException;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use Illuminate\Support\Facades\DB;

class VoteOnRequest
{
    private const int DOWNVOTE_WINDOW_HOURS = 1;

    /**
     * Casts, changes or (with a null direction) retracts the member's vote.
     */
    public function __invoke(Party $party, PartyMember $member, TrackRequest $request, ?VoteDirection $direction): TrackRequest
    {
        $changed = DB::transaction(function () use ($party, $member, $request, $direction): bool {
            $party = Party::query()->whereKey($party->id)->sharedLock()->firstOrFail();

            if ($party->state === PartyState::Ended) {
                throw RequestRefusedException::partyEndedVotingClosed();
            }

            $lockedMember = PartyMember::query()->whereKey($member->id)->lockForUpdate()->first();

            if ($lockedMember === null || $lockedMember->banned) {
                throw RequestRefusedException::banned();
            }

            $request = TrackRequest::query()->whereKey($request->id)->firstOrFail();

            $this->guardVotable($request);

            $existing = RequestVote::query()
                ->where('track_request_id', $request->id)
                ->where('party_member_id', $member->id)
                ->first();

            if ($direction === null) {
                $existing?->delete();

                return $existing !== null;
            }

            if ($existing?->value === $direction->weight()) {
                return false;
            }

            if ($direction === VoteDirection::Down) {
                $this->guardDownvoteAllowance($party, $member);
            }

            if ($existing === null) {
                RequestVote::query()->create([
                    'track_request_id' => $request->id,
                    'party_member_id' => $member->id,
                    'value' => $direction->weight(),
                ]);
            } else {
                $existing->forceFill(['value' => $direction->weight()])->save();
            }

            return true;
        });

        if ($changed) {
            BroadcastPartyQueue::dispatch($party->code);
            VoteCast::dispatch($party, $request, $member, $direction);
        }

        return TrackRequest::query()
            ->whereKey($request->id)
            ->with('requester.user')
            ->withSum('votes as score', 'value')
            ->withSum(['votes as my_vote' => fn ($query) => $query->where('party_member_id', $member->id)], 'value')
            ->firstOrFail();
    }

    private function guardVotable(TrackRequest $request): void
    {
        if ($request->status === RequestStatus::UpNext) {
            throw VoteRefusedException::upNextLocked();
        }

        if ($request->status !== RequestStatus::Queued) {
            throw VoteRefusedException::notVotable();
        }
    }

    private function guardDownvoteAllowance(Party $party, PartyMember $member): void
    {
        if (! $party->downvotes) {
            throw VoteRefusedException::downvotesDisabled();
        }

        $cap = $party->downvotes_per_hour;

        if ($cap === null) {
            return;
        }

        $windowStart = now()->subHours(self::DOWNVOTE_WINDOW_HOURS);

        $recent = RequestVote::query()
            ->where('party_member_id', $member->id)
            ->where('value', VoteDirection::Down->weight())
            ->where('updated_at', '>', $windowStart)
            ->whereHas('request', fn ($query) => $query->where('party_id', $party->id))
            ->oldest('updated_at')
            ->get();

        if ($recent->count() >= $cap) {
            $oldest = $recent->get(max($recent->count() - $cap, 0));

            throw VoteRefusedException::downvoteCapReached($oldest?->updated_at?->copy()->addHours(self::DOWNVOTE_WINDOW_HOURS));
        }
    }
}

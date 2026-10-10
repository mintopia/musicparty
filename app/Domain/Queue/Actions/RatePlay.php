<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use Illuminate\Support\Facades\DB;

class RatePlay
{
    /**
     * Likes, dislikes, changes or (with a null direction) retracts the member's rating.
     */
    public function __invoke(PartyMember $member, Play $play, ?VoteDirection $direction): Play
    {
        $changed = DB::transaction(function () use ($member, $play, $direction): bool {
            $party = Party::query()->whereKey($play->party_id)->sharedLock()->firstOrFail();

            if ($party->state === PartyState::Ended) {
                throw RequestRefusedException::partyEnded();
            }

            $locked = PartyMember::query()->whereKey($member->id)->lockForUpdate()->first();

            if ($locked === null || $locked->banned) {
                throw RequestRefusedException::banned();
            }

            $existing = Rating::query()
                ->where('play_id', $play->id)
                ->where('party_member_id', $member->id)
                ->first();

            if ($direction === null) {
                $existing?->delete();

                return $existing !== null && $this->isPlaying($play);
            }

            if ($existing?->value === $direction->weight()) {
                return false;
            }

            Rating::query()->updateOrCreate(
                ['play_id' => $play->id, 'party_member_id' => $member->id],
                ['value' => $direction->weight()],
            );

            return $this->isPlaying($play);
        });

        if ($changed) {
            BroadcastPartyQueue::dispatch((string) Party::query()->whereKey($play->party_id)->value('code'));
        }

        return Play::query()
            ->whereKey($play->id)
            ->withHistoryRelations()
            ->withRatingSummary($member)
            ->firstOrFail();
    }

    private function isPlaying(Play $play): bool
    {
        return TrackRequest::query()
            ->whereKey($play->track_request_id)
            ->where('status', RequestStatus::Playing)
            ->exists();
    }
}

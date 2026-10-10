<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Exceptions\RatingRefusedException;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use App\Jobs\BroadcastPartyQueue;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PlayRating;
use App\Models\TrackRequest;
use Illuminate\Support\Facades\DB;

class RateNowPlaying
{
    /**
     * Likes (up), dislikes (down), changes or (with a null direction) retracts the member's rating.
     */
    public function __invoke(Party $party, PartyMember $member, TrackRequest $request, ?VoteDirection $direction): TrackRequest
    {
        $changed = DB::transaction(function () use ($party, $member, $request, $direction): bool {
            Party::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();

            if ($member->fresh()?->banned !== false) {
                throw RequestRefusedException::banned();
            }

            $request = TrackRequest::query()->whereKey($request->id)->firstOrFail();

            if ($request->status !== RequestStatus::Playing) {
                throw RatingRefusedException::notPlaying();
            }

            $existing = PlayRating::query()
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

            if ($existing === null) {
                PlayRating::query()->create([
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
        }

        return TrackRequest::query()
            ->whereKey($request->id)
            ->with('requester.user')
            ->withSum('votes as score', 'value')
            ->withCount([
                'ratings as likes' => fn ($query) => $query->where('value', '>', 0),
                'ratings as dislikes' => fn ($query) => $query->where('value', '<', 0),
            ])
            ->withSum(['ratings as my_rating' => fn ($query) => $query->where('party_member_id', $member->id)], 'value')
            ->firstOrFail();
    }
}

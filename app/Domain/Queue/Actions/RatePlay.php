<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\VoteDirection;
use App\Models\PartyMember;
use App\Models\Play;
use App\Models\Rating;
use Illuminate\Support\Facades\DB;

class RatePlay
{
    /**
     * Likes, dislikes, changes or (with a null direction) retracts the member's rating.
     */
    public function __invoke(PartyMember $member, Play $play, ?VoteDirection $direction): Play
    {
        DB::transaction(function () use ($member, $play, $direction): void {
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

                return;
            }

            Rating::query()->updateOrCreate(
                ['play_id' => $play->id, 'party_member_id' => $member->id],
                ['value' => $direction->weight()],
            );
        });

        return Play::query()
            ->whereKey($play->id)
            ->withHistoryRelations()
            ->withRatingSummary($member)
            ->firstOrFail();
    }
}

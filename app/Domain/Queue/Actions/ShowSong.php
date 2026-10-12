<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Data\SongDetail;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\TrackRequest;

class ShowSong
{
    /**
     * @throws RequestRefusedException
     */
    public function __invoke(User $user, Party $party, TrackRequest $trackRequest): SongDetail
    {
        $member = $party->memberFor($user) ?? throw RequestRefusedException::notAMember();

        if ($member->banned) {
            throw RequestRefusedException::banned();
        }

        abort_unless($trackRequest->party_id === $party->id, 404);

        $trackRequest->load([
            'party',
            'requester.user',
            'votes.member.user',
            'play.memberRatings.member.user',
        ]);

        $appearances = TrackRequest::query()
            ->where('party_id', $party->id)
            ->whereKeyNot($trackRequest->id)
            ->where(function ($query) use ($trackRequest): void {
                $query->where('provider_track_id', $trackRequest->provider_track_id);

                if ($trackRequest->isrc !== null) {
                    $query->orWhere('isrc', $trackRequest->isrc);
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return new SongDetail($trackRequest, $appearances, $user->can('moderate', $party));
    }
}

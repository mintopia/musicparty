<?php

namespace App\Domain\Queue\Actions;

use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\SongRating;
use App\Models\User;

readonly class RatePlayedSong
{
    /**
     * @throws RequestRefusedException
     */
    public function __invoke(Party $party, User $user, PlayedSong $playedSong, int $rating): ?SongRating
    {
        $member = $party->memberFor($user) ?? throw RequestRefusedException::notAMemberToRate();

        if ($member->fresh()?->banned !== false) {
            throw RequestRefusedException::bannedFromRating();
        }

        if ($rating < 0) {
            return $playedSong->dislike($user);
        }

        if ($rating > 0) {
            return $playedSong->like($user);
        }

        return null;
    }
}

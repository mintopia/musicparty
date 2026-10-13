<?php

namespace App\Services;

use App\Models\UpcomingSong;
use App\Models\User;
use Illuminate\Support\Collection;
use stdClass;

class UpcomingSongAugmentService
{
    /**
     * @param  Collection<int, UpcomingSong>  $items
     * @return Collection<int, stdClass>
     */
    public function augmentCollection(Collection $items, User $user): Collection
    {
        $ids = $items->pluck('id');
        $votes = $user->votes()->whereIn('upcoming_song_id', $ids)->get();
        /** @var array<int, stdClass> $augmentedData */
        $augmentedData = [];
        foreach ($votes as $vote) {
            $augmentedData[$vote->upcoming_song_id] = (object) [
                'vote' => $vote,
            ];
        }

        return collect($augmentedData);
    }

    public function augment(UpcomingSong $song, User $user): object
    {
        return (object) [
            'vote' => $song->votes()->whereUserId($user->id)->first(),
        ];
    }
}

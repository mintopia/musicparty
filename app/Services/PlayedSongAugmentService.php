<?php

namespace App\Services;

use App\Models\PlayedSong;
use App\Models\User;
use Illuminate\Support\Collection;

class PlayedSongAugmentService
{
    public function augmentCollection(Collection $items, User $user): Collection
    {
        $ids = $items->pluck('id');
        $ratings = $user->ratings()->whereIn('played_song_id', $ids)->get();
        $augmentedData = [];
        foreach ($ratings as $rating) {
            $augmentedData[$rating->upcoming_song_id] = (object)[
                'rated' => $rating,
            ];
        }
        return collect($augmentedData);
    }

    public function augment(PlayedSong $song, User $user): object
    {
        return (object)[
            'rated' => $song->ratings()->whereUserId($user->id)->first(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SongRatingRequest;
use App\Http\Resources\V1\PlayedSongResource;
use App\Models\Party;
use App\Models\PlayedSong;

class SongRatingController extends Controller
{
    public function store(SongRatingRequest $request, Party $party, PlayedSong $playedsong)
    {
        $current = $playedsong->party->current();
        if ($current->id != $playedsong->id) {
            abort(400);
        }
        $rating = null;
        if ($request->input('rating') < 0) {
            $rating = $playedsong->dislike($request->user());
        } elseif ($request->input('rating') > 0) {
            $rating = $playedsong->like($request->user());
        }
        $resource = new PlayedSongResource($playedsong);
        $resource->augment((object)[
            'rating' => $rating,
        ]);
        return $resource;
    }
}

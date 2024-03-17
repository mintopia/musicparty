<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SongRatingRequest;
use App\Http\Resources\V1\SongRatingResource;
use App\Models\Party;
use App\Models\PlayedSong;

class SongRatingController extends Controller
{
    public function store(SongRatingRequest $request, Party $party, PlayedSong $playedsong)
    {
        if ($request->input('rating') < 0) {
            $rating = $playedsong->like($request->user());
        } elseif($request->input('vote') > 0) {
            $rating = $playedsong->dislike($request->user());
        } else {
            $rating = $request->user()->ratings()->wherePlayedSongId($playedsong->id)->first();
            if ($rating) {
                $rating->delete();
                $rating = null;
            }
        }
        $resource = new SongRatingResource($playedsong);
        $resource->augment((object)[
            'rating' => $rating,
        ]);
        return $resource;
    }
}

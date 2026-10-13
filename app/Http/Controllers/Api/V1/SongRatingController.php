<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SongRatingRequest;
use App\Http\Resources\V1\PlayedSongResource;
use App\Models\Party;
use App\Models\PlayedSong;

class SongRatingController extends Controller
{
    public function store(SongRatingRequest $request, Party $party, PlayedSong $playedsong): PlayedSongResource
    {
        $current = $playedsong->party->current();
        if ($current === null || ! $current->is($playedsong)) {
            abort(400);
        }
        $rating = null;
        if ($request->input('rating') < 0) {
            $rating = $playedsong->dislike($this->resolveAuthenticatedUser($request));
        } elseif ($request->input('rating') > 0) {
            $rating = $playedsong->like($this->resolveAuthenticatedUser($request));
        }
        $resource = new PlayedSongResource($playedsong);
        $resource->augment((object) [
            'rating' => $rating,
        ]);

        return $resource;
    }
}

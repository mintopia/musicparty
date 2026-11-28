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
        $user = $this->resolveAuthenticatedUser($request);
        abort_unless($party->canParticipate($user), 403);
        $current = $playedsong->party->current();
        if ($current === null || ! $current->is($playedsong)) {
            abort(400);
        }
        $rating = $playedsong->setRating($user, $request->integer('rating'));
        $resource = new PlayedSongResource($playedsong->refresh());
        $resource->augment((object) [
            'rating' => $rating,
        ]);

        return $resource;
    }
}

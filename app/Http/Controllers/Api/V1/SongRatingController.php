<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Queue\Actions\RatePlayedSong;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SongRatingRequest;
use App\Http\Resources\V1\PlayedSongResource;
use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SongRatingController extends Controller
{
    public function store(SongRatingRequest $request, RatePlayedSong $ratePlayedSong, Party $party, PlayedSong $playedsong): PlayedSongResource|JsonResponse
    {
        $current = $playedsong->party->current();
        if ($current->id != $playedsong->id) {
            abort(400);
        }

        $user = $request->user();
        assert($user instanceof User);

        try {
            $rating = $ratePlayedSong($party, $user, $playedsong, (int) $request->input('rating'));
        } catch (RequestRefusedException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }

        $resource = new PlayedSongResource($playedsong);
        $resource->augment((object) [
            'rating' => $rating,
        ]);

        return $resource;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUpcomingSongRequest;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\Vote;
use App\Transformers\Api\V1\UpcomingSongTransformer;
use Illuminate\Http\Request;

class UpcomingSongController extends Controller
{
    public function index(Request $request, Party $party)
    {
        $query = $party->upcoming();

        // Filters
        $query = $query->whereNull('queued_at');

        // Default sorting and pagination
        $query = $query->orderBy('votes', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC');
        $upcoming = $query->paginate(20);

        // Handy shortcut for user votes
        $votes = [];
        $ucSongIds = collect($upcoming->items())->pluck('id');
        if ($ucSongIds) {
            $votes = Vote::whereUserId($request->user()->id)
                ->whereIn('upcoming_song_id', $ucSongIds)
                ->get()
                ->pluck('upcoming_song_id')
                ->toArray();
        }
        return fractal($upcoming, new UpcomingSongTransformer($votes))->respond();
    }

    public function store(StoreUpcomingSongRequest $request, Party $party)
    {
        $upcoming = $party->upcoming()
            ->whereNull('queued_at')
            ->whereHas('song', function($query) use ($request) {
                $query->where('spotify_id', $request->input('spotify_id'));
            })->first();
        if (!$upcoming) {
            $track = $party->user->getSpotifyApi()->getTrack($request->input('spotify_id'));
            if (!$track) {
                abort(404);
            }
            $song = Song::fromSpotify($track);
            $upcoming = new UpcomingSong();
            $upcoming->song()->associate($song);
            $upcoming->party()->associate($party);
            $upcoming->save();
        }
        $upcoming->vote($request->user());
        return fractal($upcoming, new UpcomingSongTransformer([$upcoming->id]))->respond(201);
    }

    public function show(Request $request, Party $party, UpcomingSong $upcoming)
    {
        if ($upcoming->party_id != $party->id) {
            abort(404);
        }
        if ($upcoming->queued_at) {
            abort(404);
        }
        $votes = [];
        if ($upcoming->hasVoted($request->user())) {
            $votes[] = $upcoming->id;
        }
        return fractal($upcoming, new UpcomingSongTransformer($votes))->respond();
    }

    public function destroy(Party $party, UpcomingSong $upcoming)
    {
        if ($upcoming->party_id != $party->id) {
            abort(404);
        }
        if ($upcoming->queued_at) {
            abort(404);
        }
        $upcoming->delete();
        return response()->noContent();
    }
}

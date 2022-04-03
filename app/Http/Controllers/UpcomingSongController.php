<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\Vote;
use App\Services\SpotifySearchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use SpotifyWebAPI\SpotifyWebAPIException;

class UpcomingSongController extends Controller
{
    protected function search(Request $request, Party $party)
    {
        if ($request->input('query')) {
            $searchService = new SpotifySearchService($party);
            $results = $searchService->search(
                $request->input('query'),
                $request->input('page', 1),
                $request->input('perPage', 20)
            );
        } else {
            $results = new LengthAwarePaginator([], 0, $request->input('page', 1), $request->input('perPage', 20));
        }
        return view ('upcomingsongs.search', [
            'party' => $party,
            'tracks' => $results,
            'query' => $request->input('query', ''),
        ]);
    }

    protected function vote(Request $request, Party $party)
    {
        $upcoming = $party->upcoming()->whereNull('queued_at')->whereHas('song', function ($query) use ($request) {
            $query->where('spotify_id', $request->input('id'));
        })->first();

        if (!$upcoming) {
            try {
                $song = $party->user->getSpotifyApi()->getTrack($request->input('id'), [
                    'market' => $party->user->market,
                ]);
            } catch (SpotifyWebAPIException $e) {
                abort(404);
            }
            $song = Song::fromSpotify($song);
            $upcoming = new UpcomingSong;
            $upcoming->party()->associate($party);
            $upcoming->song()->associate($song);
            $upcoming->save();
        }

        if ($upcoming->hasVoted($request->user())) {
            return response()->redirectToRoute('parties.guest', $party->code)->with('errorMessage', 'You have already voted for this song');
        }

        $vote = new Vote;
        $vote->upcoming_song()->associate($upcoming);
        $vote->user()->associate($request->user());
        $vote->save();

        return response()->redirectToRoute('parties.guest', $party->code)->with('successMessage', 'Your vote has been counted');
    }
}

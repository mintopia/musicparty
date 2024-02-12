<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpcomingSongRequest;
use App\Http\Resources\V1\Collections\UpcomingSongResourceCollection;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\Vote;
use App\Services\RequestCheckService;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Http\Request;

class UpcomingSongController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
        $this->authorizeResource(UpcomingSong::class, 'upcomingsong');
    }

    public function index(UpcomingSongAugmentService $augmentService, Request $request, Party $party)
    {
        $params = [];
        if ($request->has('per_page')) {
            $params['per_page'] = $request->input('per_page');
        }
        $page = $party->upcoming()
            ->with(['user', 'song'])
            ->whereNull('queued_at')
            ->orderBy('score', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->paginate($params['per_page'] ?? 20)->appends($params);
        $collection = new UpcomingSongResourceCollection($page);

        $augmentedData = $augmentService->augmentCollection(collect($page->items()), $request->user());
        $collection->augment($augmentedData->toArray());

        return $collection;
    }

    public function show(UpcomingSongAugmentService $augmentService, Request $request, Party $party, UpcomingSong $upcomingsong)
    {
        $resource = new UpcomingSongResource($upcomingsong);
        $data = $augmentService->augment($upcomingsong, $request->user());
        $resource->augment($data);
        return $resource;
    }

    public function store(UpcomingSongAugmentService $augmentService, UpcomingSongRequest $request, Party $party)
    {
        $upcoming = $party->upcoming()->whereNull('queued_at')->whereHas('song', function($query) use ($request) {
            $query->whereSpotifyId($request->input('spotify_id'));
        })->first();
        if (!$upcoming) {
            $spotifySong = $party->user->getSpotifyApi()->getTrack($request->input('spotify_id'));
            if (!$spotifySong) {
                return response()->json([
                    'message' => 'Unable to find the song in Spotify.',
                    'errors' => [
                        'spotify_id' => [
                            'Unable to find the song in Spotify.'
                        ]
                    ]
                ], 422);
            }

            $member = $party->members()->whereUserId($request->user()->id)->first();
            if (!$member) {
                abort(403);
            }
            $checkService = new RequestCheckService($party, $member);
            $checkResponse = $checkService->checkSong($spotifySong);
            if (!$checkResponse->allowed) {
                return response()->json([
                    'message' => $checkResponse->reason,
                    'errors' => [
                        'spotify_id' => [
                            $checkResponse->reason,
                        ],
                    ],
                ]);
            }

            $song = Song::fromSpotify($spotifySong);
            $upcoming = new UpcomingSong();
            $upcoming->party()->associate($party);
            $upcoming->user()->associate($request->user());
            $upcoming->song()->associate($song);
            $upcoming->save();
        }
        $vote = $upcoming->votes()->whereUserId($request->user()->id)->first();
        if (!$vote) {
            $vote = new Vote();
            $vote->user()->associate($request->user());
            $vote->upcomingSong()->associate($upcoming);
        }
        $vote->value = 1;
        $vote->save();

        $resource = new UpcomingSongResource($upcoming);
        $data = $augmentService->augment($upcoming, $request->user());
        $resource->augment($data);
        return $resource;
    }

    public function destroy(Party $party, UpcomingSong $upcomingsong)
    {
        if ($upcomingsong->queued_at) {
            abort(400, 'Unable to delete an upcoming song that has been queued');
        }
        $upcomingsong->delete();
        return response()->noContent();
    }
}

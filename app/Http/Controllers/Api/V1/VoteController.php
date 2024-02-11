<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VoteRequest;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\UpcomingSong;

class VoteController extends Controller
{
    public function store(VoteRequest $request, Party $party, UpcomingSong $upcomingsong)
    {
        if ($request->input('vote') < 0) {
            $vote = $upcomingsong->downvote($request->user());
        } elseif($request->input('vote') > 0) {
            $vote = $upcomingsong->upvote($request->user());
        } else {
            $vote = $request->user()->votes()->whereUpcomingSongId($upcomingsong->id)->first();
            if ($vote) {
                $vote->delete();
                $vote = null;
            }
        }
        $resource = new UpcomingSongResource($upcomingsong);
        $resource->augment((object)[
            'vote' => $vote,
        ]);
        return $resource;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\VoteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VoteRequest;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\UpcomingSong;

class VoteController extends Controller
{
    public function store(VoteRequest $request, Party $party, UpcomingSong $upcomingsong): UpcomingSongResource
    {
        try {
            if ($request->input('vote') < 0) {
                $vote = $upcomingsong->downvote($this->resolveAuthenticatedUser($request));
            } elseif ($request->input('vote') > 0) {
                $vote = $upcomingsong->upvote($this->resolveAuthenticatedUser($request));
            } else {
                $vote = $this->resolveAuthenticatedUser($request)->votes()->whereUpcomingSongId($upcomingsong->id)->first();
                if ($vote) {
                    $vote->delete();
                    $vote = null;
                }
            }
            $resource = new UpcomingSongResource($upcomingsong);
            $resource->augment((object) [
                'vote' => $vote,
            ]);

            return $resource;
        } catch (VoteException $ex) {
            abort(400, $ex->getMessage());
        }
    }
}

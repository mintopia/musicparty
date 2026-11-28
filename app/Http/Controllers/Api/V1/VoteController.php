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
        $user = $this->resolveAuthenticatedUser($request);
        abort_unless($party->canParticipate($user), 403);

        try {
            if ($request->input('vote') < 0) {
                $vote = $upcomingsong->downvote($user);
                abort_if($vote === null, 403, 'Downvotes are disabled for this party');
            } elseif ($request->input('vote') > 0) {
                $vote = $upcomingsong->upvote($user);
            } else {
                $vote = $user->votes()->whereUpcomingSongId($upcomingsong->id)->first();
                if ($vote) {
                    $vote->delete();
                    $vote = null;
                }
            }
            $resource = new UpcomingSongResource($upcomingsong->refresh());
            $resource->augment((object) [
                'vote' => $vote,
            ]);

            return $resource;
        } catch (VoteException $ex) {
            abort(400, $ex->getMessage());
        }
    }
}

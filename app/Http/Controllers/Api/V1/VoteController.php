<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\VoteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VoteRequest;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\Queue\QueueListing;
use Illuminate\Contracts\Cache\LockTimeoutException;

class VoteController extends Controller
{
    public function store(VoteRequest $request, Party $party, UpcomingSong $upcomingsong, QueueListing $queue): UpcomingSongResource
    {
        $user = $this->resolveAuthenticatedUser($request);
        $input = (int) $request->input('vote');
        if ($input !== 0 && $party->getMember($user)->role->code === 'banned') {
            abort(403, 'You are banned from voting for songs');
        }

        try {
            if ($input < 0) {
                $vote = $upcomingsong->downvote($user);
                abort_if($vote === null, 403, 'Downvotes are disabled for this party');
            } elseif ($input > 0) {
                $vote = $upcomingsong->upvote($user);
            } else {
                $vote = $user->votes()->whereUpcomingSongId($upcomingsong->id)->first();
                if ($vote) {
                    $vote->delete();
                    $vote = null;
                }
            }
            $upcomingsong->refresh();
            $resource = new UpcomingSongResource($upcomingsong);
            $resource->position($queue->position($party, $upcomingsong));
            $resource->augment((object) [
                'vote' => $vote,
            ]);

            return $resource;
        } catch (LockTimeoutException) {
            abort(429, 'Too many votes at once. Try again in a moment');
        } catch (VoteException $ex) {
            abort(400, $ex->getMessage());
        }
    }
}

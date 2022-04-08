<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\UpcomingSong;
use Illuminate\Http\Request;

class VoteController extends Controller
{

    public function store(Request $request, Party $party, UpcomingSong $upcoming)
    {
        if ($upcoming->party->id != $party->id) {
            abort(404);
        }
        if ($upcoming->queued_at) {
            abort(404);
        }

        $vote = $upcoming->vote($request->user());
        if ($vote) {
            return response()->noContent();
        } else {
            abort(419, 'Unable to vote for this track');
        }
    }

}

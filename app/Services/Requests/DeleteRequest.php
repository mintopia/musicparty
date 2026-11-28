<?php

namespace App\Services\Requests;

use App\Events\FlaggedRequestResolved;
use App\Models\UpcomingSong;
use App\Services\Playback\PartyDashboardCache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DeleteRequest
{
    /**
     * Played Songs link back to their Request with null-on-delete, so a Request that was
     * Sent to the Player is refused rather than losing its history.
     */
    public function handle(UpcomingSong $request): void
    {
        Gate::authorize('delete', $request);

        if ($request->queued_at !== null) {
            throw new ConflictHttpException('Unable to delete a Request that has been sent to the Player');
        }

        $party = $request->party;
        $id = $request->id;
        $wasFlagged = $request->flagged_at !== null;

        $request->delete();

        if ($wasFlagged) {
            FlaggedRequestResolved::dispatch($party, $id);
        }
        PartyDashboardCache::forget($party);
        $party->pushUpdate();
    }
}

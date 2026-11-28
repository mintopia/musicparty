<?php

namespace App\Services\Requests;

use App\Events\FlaggedRequestResolved;
use App\Events\UpcomingSong\RemovedEvent;
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
    public function handle(UpcomingSong $request, bool $requireFlagged = false, string $ability = 'delete'): void
    {
        Gate::authorize($ability, $request);

        if ($request->queued_at !== null) {
            throw new ConflictHttpException('Unable to delete a Request that has been sent to the Player');
        }

        $party = $request->party;
        $id = $request->id;
        $wasFlagged = $request->flagged_at !== null;

        $query = UpcomingSong::query()->whereKey($id)->whereNull('queued_at');
        if ($requireFlagged) {
            $query->whereNotNull('flagged_at');
        }
        if ($query->delete() === 0) {
            throw new ConflictHttpException($requireFlagged ? 'This Request has already been handled' : 'Unable to delete a Request that has been sent to the Player');
        }

        RemovedEvent::dispatch($party, $id);
        if ($wasFlagged || $requireFlagged) {
            FlaggedRequestResolved::dispatch($party, $id);
        }
        PartyDashboardCache::forget($party);
        $party->pushUpdate();
    }
}

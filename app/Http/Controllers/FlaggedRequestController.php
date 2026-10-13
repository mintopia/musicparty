<?php

namespace App\Http\Controllers;

use App\Events\FlaggedRequestRejected;
use App\Events\FlaggedRequestResolved;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\Requests\FlaggedRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlaggedRequestController extends Controller
{
    public function index(Party $party, FlaggedRequests $flagged): Response
    {
        $this->authorize('update', $party);

        return Inertia::render('Parties/Flagged', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'flagged' => $flagged->listFor($party),
        ]);
    }

    public function approve(Party $party, UpcomingSong $upcomingSong): RedirectResponse
    {
        $this->authorize('approve', $upcomingSong);
        $this->ensureFlagged($upcomingSong);

        $upcomingSong->forceFill(['flagged_at' => null, 'flag_reason' => null])->save();
        FlaggedRequestResolved::dispatch($party, $upcomingSong->id);

        return back();
    }

    public function reject(Request $request, Party $party, UpcomingSong $upcomingSong): RedirectResponse
    {
        $this->authorize('reject', $upcomingSong);
        $this->ensureFlagged($upcomingSong);
        $validated = $request->validate(['message' => ['nullable', 'string', 'max:255']]);

        $message = $validated['message'] ?? $upcomingSong->flag_reason;
        $userId = $upcomingSong->user_id;
        $id = $upcomingSong->id;
        $trackName = $upcomingSong->song->name;
        $upcomingSong->delete();
        FlaggedRequestResolved::dispatch($party, $id);
        if ($userId !== null) {
            FlaggedRequestRejected::dispatch($party, $userId, $id, $trackName, $message);
        }

        return back();
    }

    public function withdraw(Party $party, UpcomingSong $upcomingSong): RedirectResponse
    {
        $this->authorize('withdraw', $upcomingSong);

        $id = $upcomingSong->id;
        $upcomingSong->delete();
        FlaggedRequestResolved::dispatch($party, $id);

        return back();
    }

    protected function ensureFlagged(UpcomingSong $upcomingSong): void
    {
        abort_if($upcomingSong->flagged_at === null, 404);
    }
}

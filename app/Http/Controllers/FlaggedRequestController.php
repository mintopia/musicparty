<?php

namespace App\Http\Controllers;

use App\Events\FlaggedRequestRejected;
use App\Events\FlaggedRequestResolved;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\Requests\FlaggedRequests;
use App\Services\Requests\ModerationTabs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlaggedRequestController extends Controller
{
    public function index(Request $request, Party $party, FlaggedRequests $flagged, ModerationTabs $tabs): Response
    {
        $this->authorize('moderate', $party);

        return Inertia::render('Parties/Flagged', [
            'party' => ['code' => $party->code, 'name' => $party->name, 'canModerate' => true, 'canManage' => $request->user()?->can('update', $party) ?? false, 'flagRequests' => $party->flag_requests],
            'flagged' => $flagged->listFor($party),
            'moderationTabs' => $tabs->for($party),
            'playerState' => $party->getState(),
        ]);
    }

    public function approve(Party $party, UpcomingSong $upcomingSong): RedirectResponse
    {
        $this->authorize('approve', $upcomingSong);
        $this->ensureFlagged($upcomingSong);
        $this->resolveApproved($party, $upcomingSong);

        return back();
    }

    public function reject(Request $request, Party $party, UpcomingSong $upcomingSong): RedirectResponse
    {
        $this->authorize('reject', $upcomingSong);
        $this->ensureFlagged($upcomingSong);
        $validated = $request->validate(['message' => ['nullable', 'string', 'max:255']]);
        $this->resolveRejected($party, $upcomingSong, $validated['message'] ?? null);

        return back();
    }

    public function bulkApprove(Request $request, Party $party): RedirectResponse
    {
        foreach ($this->bulkTargets($request, $party, 'approve') as $upcomingSong) {
            $this->resolveApproved($party, $upcomingSong);
        }

        return back();
    }

    public function bulkReject(Request $request, Party $party): RedirectResponse
    {
        $message = $request->validate(['message' => ['nullable', 'string', 'max:255']])['message'] ?? null;
        foreach ($this->bulkTargets($request, $party, 'reject') as $upcomingSong) {
            $this->resolveRejected($party, $upcomingSong, $message);
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

    /**
     * @return Collection<int, UpcomingSong>
     */
    protected function bulkTargets(Request $request, Party $party, string $ability): Collection
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ])['ids'];

        $targets = $party->upcoming()->whereNull('queued_at')->whereNotNull('flagged_at')->whereIn('id', $ids)->with('song')->get();
        abort_if($targets->count() !== count(array_unique($ids)), 404);
        foreach ($targets as $upcomingSong) {
            $this->authorize($ability, $upcomingSong);
        }

        return $targets;
    }

    protected function resolveApproved(Party $party, UpcomingSong $upcomingSong): void
    {
        $upcomingSong->forceFill(['flagged_at' => null, 'flag_reason' => null])->save();
        FlaggedRequestResolved::dispatch($party, $upcomingSong->id);
    }

    protected function resolveRejected(Party $party, UpcomingSong $upcomingSong, ?string $message): void
    {
        $message ??= $upcomingSong->flag_reason;
        $userId = $upcomingSong->user_id;
        $id = $upcomingSong->id;
        $trackName = $upcomingSong->song->name;
        $upcomingSong->delete();
        FlaggedRequestResolved::dispatch($party, $id);
        if ($userId !== null) {
            FlaggedRequestRejected::dispatch($party, $userId, $id, $trackName, $message);
        }
    }

    protected function ensureFlagged(UpcomingSong $upcomingSong): void
    {
        abort_if($upcomingSong->flagged_at === null, 404);
    }
}

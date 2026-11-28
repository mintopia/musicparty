<?php

namespace App\Http\Controllers;

use App\Events\FlaggedRequestRejected;
use App\Events\FlaggedRequestResolved;
use App\Events\UpcomingSong\UpdatedEvent;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Models\Vote;
use App\Services\Requests\DeleteRequest;
use App\Services\Requests\FlaggedRequests;
use App\Services\Requests\ModerationTabs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FlaggedRequestController extends Controller
{
    public const ALREADY_HANDLED = 'This Request has already been handled';

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
        $this->resolveApproved($party, $upcomingSong);

        return back();
    }

    public function reject(Request $request, Party $party, UpcomingSong $upcomingSong, DeleteRequest $deleteRequest): RedirectResponse
    {
        $this->authorize('reject', $upcomingSong);
        $validated = $request->validate(['message' => ['nullable', 'string', 'max:255']]);
        $this->resolveRejected($party, $upcomingSong, $validated['message'] ?? null, $deleteRequest);

        return back();
    }

    public function bulkApprove(Request $request, Party $party): RedirectResponse
    {
        foreach ($this->bulkTargets($request, $party, 'approve') as $upcomingSong) {
            try {
                $this->resolveApproved($party, $upcomingSong);
            } catch (ConflictHttpException) {
                continue;
            }
        }

        return back();
    }

    public function bulkReject(Request $request, Party $party, DeleteRequest $deleteRequest): RedirectResponse
    {
        $message = $request->validate(['message' => ['nullable', 'string', 'max:255']])['message'] ?? null;
        foreach ($this->bulkTargets($request, $party, 'reject') as $upcomingSong) {
            try {
                $this->resolveRejected($party, $upcomingSong, $message, $deleteRequest);
            } catch (ConflictHttpException) {
                continue;
            }
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
        DB::transaction(function () use ($upcomingSong): void {
            $cleared = UpcomingSong::query()
                ->whereKey($upcomingSong->id)
                ->whereNull('queued_at')
                ->whereNotNull('flagged_at')
                ->update(['flagged_at' => null, 'flag_reason' => null]);
            if ($cleared === 0) {
                throw new ConflictHttpException(self::ALREADY_HANDLED);
            }

            if ($upcomingSong->user_id !== null) {
                Vote::query()->updateOrCreate(['upcoming_song_id' => $upcomingSong->id, 'user_id' => $upcomingSong->user_id], ['value' => 1]);
            }
            $upcomingSong->refresh();
            $upcomingSong->updateScore();
        });

        UpdatedEvent::dispatch($upcomingSong);
        FlaggedRequestResolved::dispatch($party, $upcomingSong->id);
    }

    protected function resolveRejected(Party $party, UpcomingSong $upcomingSong, ?string $message, DeleteRequest $deleteRequest): void
    {
        $message ??= $upcomingSong->flag_reason;
        $userId = $upcomingSong->user_id;
        $id = $upcomingSong->id;
        $trackName = $upcomingSong->song->name;
        $deleteRequest->handle($upcomingSong, requireFlagged: true);
        if ($userId !== null) {
            FlaggedRequestRejected::dispatch($party, $userId, $id, $trackName, $message);
        }
    }
}

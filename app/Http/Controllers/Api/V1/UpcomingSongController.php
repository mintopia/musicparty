<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Purpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpcomingSongRequest;
use App\Http\Resources\V1\Collections\UpcomingSongResourceCollection;
use App\Http\Resources\V1\UpcomingSongResource;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\AiRequestReview\AiReviewRecorder;
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyTimezone;
use App\Services\Queue\QueueListing;
use App\Services\QueueSelector;
use App\Services\Requests\DeleteRequest;
use App\Services\Requests\FlaggedRequests;
use App\Services\Requests\RequestRules;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class UpcomingSongController extends Controller
{
    private const int REQUEST_LOCK_SECONDS = 30;

    public function __construct()
    {
        $this->authorizeResource(UpcomingSong::class, 'upcomingsong');
    }

    public function index(UpcomingSongAugmentService $augmentService, QueueListing $queue, Request $request, Party $party): UpcomingSongResourceCollection
    {
        $validated = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100']]);
        $params = isset($validated['per_page']) ? ['per_page' => (int) $validated['per_page']] : [];
        $perPage = $params['per_page'] ?? 20;
        $currentPage = Paginator::resolveCurrentPage();
        $listing = $queue->page($party, ($currentPage - 1) * $perPage, $perPage);
        $page = new LengthAwarePaginator($listing['items'], $listing['total'], $perPage, $currentPage, ['path' => Paginator::resolveCurrentPath()])->appends($params);
        $collection = new UpcomingSongResourceCollection($page);

        $augmentedData = $augmentService->augmentCollection(collect($page->items()), $this->resolveAuthenticatedUser($request));
        $collection->augment($augmentedData->toArray());
        $collection->queueStates($listing['states']);

        return $collection;
    }

    public function show(UpcomingSongAugmentService $augmentService, Request $request, Party $party, UpcomingSong $upcomingsong): UpcomingSongResource
    {
        $resource = new UpcomingSongResource($upcomingsong);
        $data = $augmentService->augment($upcomingsong, $this->resolveAuthenticatedUser($request));
        $resource->augment($data);

        return $resource;
    }

    public function store(UpcomingSongAugmentService $augmentService, UpcomingSongRequest $request, Party $party): JsonResponse|UpcomingSongResource
    {
        $member = $party->members()->whereUserId($this->resolveAuthenticatedUser($request)->id)->first();
        if (! $member instanceof PartyMember) {
            abort(403);
        }

        $user = $this->resolveAuthenticatedUser($request);
        $scheduledFor = null;
        if ($request->filled('scheduled_for')) {
            if (! $party->canBeModeratedBy($user)) {
                abort(403, 'Only a party owner or moderator can schedule a request');
            }
            $scheduledFor = Carbon::parse((string) $request->input('scheduled_for'), app(PartyTimezone::class)->dateTimeZone($party))->utc();
        }
        $trackId = (string) $request->input('provider_track_id');
        $wait = (int) config('musicparty.request_lock_wait_seconds');

        try {
            $outcome = Cache::lock("request-store:{$party->id}:{$member->id}", self::REQUEST_LOCK_SECONDS)
                ->block($wait, fn (): UpcomingSong|JsonResponse => $this->storeRequest($party, $member, $user, $trackId, $scheduledFor, $wait));
        } catch (LockTimeoutException) {
            abort(429, 'Another request is already being processed');
        }
        if ($outcome instanceof JsonResponse) {
            return $outcome;
        }
        $upcoming = $outcome;

        $resource = new UpcomingSongResource($upcoming);
        $data = $augmentService->augment($upcoming, $this->resolveAuthenticatedUser($request));
        $resource->augment($data);

        return $resource;
    }

    private function waitingRequest(Party $party, string $trackId): ?UpcomingSong
    {
        return $party->upcoming()->whereNull('queued_at')->whereHas('song', function ($query) use ($party, $trackId) {
            $query->where(['provider_id' => $party->provider_id, 'provider_track_id' => $trackId]);
        })->first();
    }

    private function rejection(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['provider_track_id' => [$message]]], $status);
    }

    private function storeRequest(Party $party, PartyMember $member, User $user, string $trackId, ?Carbon $scheduledFor, int $wait): UpcomingSong|JsonResponse
    {
        $song = null;
        $check = null;
        $existing = $this->waitingRequest($party, $trackId);
        if ($existing?->flagged_at !== null) {
            return $this->rejection('This request is awaiting approval', 400);
        }
        if (! $existing) {
            $track = app(MusicProviderFactory::class)->forParty($party, Purpose::Control)->getTrack($trackId);
            if (! $track) {
                return $this->rejection('Unable to find the song in Spotify.', 422);
            }

            $check = app(RequestRules::class)->check($party, $member, $track);
            if (! $check->allowed) {
                return response()->json([
                    'message' => $check->reason,
                    'errors' => ['provider_track_id' => [$check->reason]],
                ])->setStatusCode(400);
            }
            $song = Song::fromTrack($track);
        }

        return app(QueueSelector::class)->withRequestLock($party, function () use ($party, $member, $user, $trackId, $scheduledFor, $song, $check): UpcomingSong|JsonResponse {
            $upcoming = $this->waitingRequest($party, $trackId);
            if ($upcoming?->flagged_at !== null) {
                return $this->rejection('This request is awaiting approval', 400);
            }
            if (! $upcoming) {
                if ($song === null) {
                    return $this->rejection('The request changed while it was being processed, please try again', 409);
                }
                if ($check->isFlagged()) {
                    $flagged = app(FlaggedRequests::class)->flag($party, $song, $user, (string) $check->reason, $scheduledFor);
                    app(AiReviewRecorder::class)->linkRequest($flagged, $user, $trackId);

                    return response()->json([
                        'message' => 'Your request is awaiting approval',
                        'awaiting_approval' => true,
                        'id' => $flagged->id,
                        'reason' => $check->reason,
                    ], 202);
                }
                $upcoming = app(QueueSelector::class)->addRequestLocked($party, $song, $user);
                app(AiReviewRecorder::class)->linkRequest($upcoming, $user, $trackId);
            }

            if ($scheduledFor !== null) {
                $upcoming->forceFill(['scheduled_for' => $scheduledFor, 'not_before' => $scheduledFor])->save();
            }

            $denial = $this->voteDenialReason($party, $member);
            if ($denial) {
                return $this->rejection($denial, 400);
            }
            $upcoming->upvote($user);

            return $upcoming;
        }, $wait);
    }

    public function destroy(Party $party, UpcomingSong $upcomingsong, DeleteRequest $deleteRequest): Response
    {
        $deleteRequest->handle($upcomingsong);

        return response()->noContent();
    }

    protected function voteDenialReason(Party $party, PartyMember $member): ?string
    {
        if (! $party->allow_requests) {
            return 'Party is not accepting requests';
        }
        if ($member->role->code === 'banned') {
            return 'You are not allowed to request any more songs';
        }

        return null;
    }
}

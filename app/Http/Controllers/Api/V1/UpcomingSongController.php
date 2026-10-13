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
use App\Services\Music\MusicProviderFactory;
use App\Services\PartyTimezone;
use App\Services\Requests\FlaggedRequests;
use App\Services\Requests\RequestRules;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class UpcomingSongController extends Controller
{
    private const int REQUEST_LOCK_SECONDS = 30;

    public function __construct()
    {
        $this->authorizeResource(UpcomingSong::class, 'upcomingsong');
    }

    public function index(UpcomingSongAugmentService $augmentService, Request $request, Party $party): UpcomingSongResourceCollection
    {
        $params = [];
        if ($request->has('per_page')) {
            $params['per_page'] = $request->input('per_page');
        }
        $page = $party->upcoming()
            ->with(['user', 'song'])
            ->whereNull('queued_at')
            ->whereNull('flagged_at')
            ->orderBy('score', 'desc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($params['per_page'] ?? 20)->appends($params);
        $collection = new UpcomingSongResourceCollection($page);

        $augmentedData = $augmentService->augmentCollection(collect($page->items()), $this->resolveAuthenticatedUser($request));
        $collection->augment($augmentedData->toArray());

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
            if (! $party->canBeManagedBy($user)) {
                abort(403, 'Only a party owner or moderator can schedule a request');
            }
            $scheduledFor = Carbon::parse((string) $request->input('scheduled_for'), app(PartyTimezone::class)->dateTimeZone($party))->utc();
        }
        $trackId = (string) $request->input('provider_track_id');
        $wait = (int) config('musicparty.request_lock_wait_seconds');

        try {
            $outcome = Cache::lock("request-store:{$party->id}:{$member->id}", self::REQUEST_LOCK_SECONDS)
                ->block($wait, fn (): UpcomingSong|JsonResponse => Cache::lock("request-store:{$party->id}:track:{$trackId}", self::REQUEST_LOCK_SECONDS)
                    ->block($wait, function () use ($party, $member, $user, $trackId, $scheduledFor): UpcomingSong|JsonResponse {
                        $upcoming = $party->upcoming()->whereNull('queued_at')->whereHas('song', function ($query) use ($party, $trackId) {
                            $query->where(['provider_id' => $party->provider_id, 'provider_track_id' => $trackId]);
                        })->first();
                        if ($upcoming?->flagged_at !== null) {
                            $message = 'This request is awaiting approval';

                            return response()->json(['message' => $message, 'errors' => ['provider_track_id' => [$message]]], 400);
                        }
                        if (! $upcoming) {
                            $track = app(MusicProviderFactory::class)->forParty($party, Purpose::Control)->getTrack($trackId);
                            if (! $track) {
                                return response()->json([
                                    'message' => 'Unable to find the song in Spotify.',
                                    'errors' => [
                                        'provider_track_id' => [
                                            'Unable to find the song in Spotify.',
                                        ],
                                    ],
                                ], 422);
                            }

                            $checkResponse = app(RequestRules::class)->check($party, $member, $track);
                            if (! $checkResponse->allowed) {
                                return response()->json([
                                    'message' => $checkResponse->reason,
                                    'errors' => [
                                        'provider_track_id' => [
                                            $checkResponse->reason,
                                        ],
                                    ],
                                ])->setStatusCode(400);
                            }

                            $song = Song::fromTrack($track);
                            if ($checkResponse->isFlagged()) {
                                $flagged = app(FlaggedRequests::class)->flag($party, $song, $user, (string) $checkResponse->reason);

                                return response()->json([
                                    'message' => 'Your request is awaiting approval',
                                    'awaiting_approval' => true,
                                    'id' => $flagged->id,
                                    'reason' => $checkResponse->reason,
                                ], 202);
                            }
                            $upcoming = new UpcomingSong;
                            $upcoming->party()->associate($party);
                            $upcoming->user()->associate($user);
                            $upcoming->song()->associate($song);
                            $upcoming->save();
                        }

                        if ($scheduledFor !== null) {
                            $upcoming->forceFill(['scheduled_for' => $scheduledFor, 'not_before' => $scheduledFor])->save();
                        }

                        $denial = $this->voteDenialReason($party, $member);
                        if ($denial) {
                            return response()->json([
                                'message' => $denial,
                                'errors' => ['provider_track_id' => [$denial]],
                            ], 400);
                        }
                        $upcoming->upvote($user);

                        return $upcoming;
                    }));
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

    public function destroy(Party $party, UpcomingSong $upcomingsong): Response
    {
        if ($upcomingsong->queued_at) {
            abort(400, 'Unable to delete an upcoming song that has been queued');
        }
        $upcomingsong->delete();

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

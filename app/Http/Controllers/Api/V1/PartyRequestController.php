<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Queue\Actions\ApproveRequest;
use App\Domain\Queue\Actions\ListPendingRequests;
use App\Domain\Queue\Actions\ListQueue;
use App\Domain\Queue\Actions\RateNowPlaying;
use App\Domain\Queue\Actions\RejectRequest;
use App\Domain\Queue\Actions\RemoveRequest;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Actions\SearchPartyProvider;
use App\Domain\Queue\Actions\VoteOnRequest;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Exceptions\VoteRefusedException;
use App\Domain\Queue\VoteDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchTracksRequest;
use App\Http\Requests\CastVoteRequest;
use App\Http\Requests\RateNowPlayingRequest;
use App\Http\Requests\RejectRequestRequest;
use App\Http\Requests\RequestTrackRequest;
use App\Http\Resources\V1\QueueEntryResource;
use App\Http\Resources\V1\SearchHitResource;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartyRequestController extends Controller
{
    public function search(SearchTracksRequest $request, SearchPartyProvider $search, Party $party): AnonymousResourceCollection|JsonResponse
    {
        try {
            $this->member($request, $party);

            return SearchHitResource::collection($search($party, $request->string('q')->toString()));
        } catch (RequestRefusedException $exception) {
            return $this->refusal($exception);
        }
    }

    public function store(RequestTrackRequest $request, RequestTrack $requestTrack, Party $party): JsonResponse
    {
        try {
            $outcome = $requestTrack($party, $this->member($request, $party), $request->string('provider_track_id')->toString());
        } catch (RequestRefusedException $exception) {
            return $this->refusal($exception);
        }

        $member = $this->member($request, $party);
        $outcome->request->loadMissing('requester.user')
            ->loadSum('votes as score', 'value')
            ->loadSum(['votes as my_vote' => fn ($query) => $query->where('party_member_id', $member->id)], 'value');

        return new QueueEntryResource($outcome->request)
            ->additional(['meta' => ['duplicate' => ! $outcome->created, 'vote_added' => $outcome->voteAdded]])
            ->response()
            ->setStatusCode($outcome->created ? 201 : 200);
    }

    public function queue(Request $request, ListQueue $listQueue, Party $party): AnonymousResourceCollection|JsonResponse
    {
        try {
            return QueueEntryResource::collection($listQueue($party, $this->member($request, $party)));
        } catch (RequestRefusedException $exception) {
            return $this->refusal($exception);
        }
    }

    public function pending(Request $request, ListPendingRequests $listPending, Party $party): AnonymousResourceCollection|JsonResponse
    {
        try {
            return QueueEntryResource::collection($listPending($this->currentUser($request), $party));
        } catch (RequestRefusedException $exception) {
            return $this->refusal($exception);
        }
    }

    public function approve(Request $request, ApproveRequest $approve, Party $party, TrackRequest $trackRequest): QueueEntryResource|JsonResponse
    {
        return $this->moderate($party, $trackRequest, fn (): TrackRequest => $approve($this->currentUser($request), $party, $trackRequest));
    }

    public function reject(RejectRequestRequest $request, RejectRequest $reject, Party $party, TrackRequest $trackRequest): QueueEntryResource|JsonResponse
    {
        return $this->moderate($party, $trackRequest, fn (): TrackRequest => $reject($this->currentUser($request), $party, $trackRequest, $request->reason()));
    }

    public function destroy(Request $request, RemoveRequest $remove, Party $party, TrackRequest $trackRequest): QueueEntryResource|JsonResponse
    {
        return $this->moderate($party, $trackRequest, fn (): TrackRequest => $remove($this->currentUser($request), $party, $trackRequest));
    }

    public function vote(CastVoteRequest $request, VoteOnRequest $vote, Party $party, TrackRequest $trackRequest): QueueEntryResource|JsonResponse
    {
        return $this->applyVote($request, $vote, $party, $trackRequest, $request->direction());
    }

    public function retractVote(Request $request, VoteOnRequest $vote, Party $party, TrackRequest $trackRequest): QueueEntryResource|JsonResponse
    {
        return $this->applyVote($request, $vote, $party, $trackRequest, null);
    }

    private function applyVote(Request $request, VoteOnRequest $vote, Party $party, TrackRequest $trackRequest, ?VoteDirection $direction): QueueEntryResource|JsonResponse
    {
        abort_unless($trackRequest->party_id === $party->id, 404);

        try {
            return new QueueEntryResource($vote($party, $this->member($request, $party), $trackRequest, $direction));
        } catch (RequestRefusedException $exception) {
            return $this->refusal($exception);
        }
    }

    public function rate(RateNowPlayingRequest $request, RateNowPlaying $rate, Party $party, TrackRequest $trackRequest): QueueEntryResource|JsonResponse
    {
        return $this->applyRating($request, $rate, $party, $trackRequest, $request->direction());
    }

    public function retractRating(Request $request, RateNowPlaying $rate, Party $party, TrackRequest $trackRequest): QueueEntryResource|JsonResponse
    {
        return $this->applyRating($request, $rate, $party, $trackRequest, null);
    }

    private function applyRating(Request $request, RateNowPlaying $rate, Party $party, TrackRequest $trackRequest, ?VoteDirection $direction): QueueEntryResource|JsonResponse
    {
        abort_unless($trackRequest->party_id === $party->id, 404);

        try {
            return new QueueEntryResource($rate($party, $this->member($request, $party), $trackRequest, $direction));
        } catch (RequestRefusedException $exception) {
            return $this->refusal($exception);
        }
    }

    /**
     * @param  callable(): TrackRequest  $decision
     */
    private function moderate(Party $party, TrackRequest $trackRequest, callable $decision): QueueEntryResource|JsonResponse
    {
        abort_unless($trackRequest->party_id === $party->id, 404);

        try {
            return new QueueEntryResource($decision());
        } catch (RequestRefusedException $exception) {
            return $this->refusal($exception);
        }
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    private function member(Request $request, Party $party): PartyMember
    {
        $user = $request->user();
        assert($user instanceof User);

        return $party->memberFor($user) ?? throw RequestRefusedException::notAMember();
    }

    private function refusal(RequestRefusedException $exception): JsonResponse
    {
        $payload = ['message' => $exception->getMessage()];

        if ($exception instanceof VoteRefusedException && $exception->retryAt !== null) {
            $payload['retry_at'] = $exception->retryAt->toIso8601String();
        }

        return response()->json($payload, $exception->status());
    }
}

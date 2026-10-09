<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Queue\Actions\ListQueue;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Actions\SearchPartyProvider;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchTracksRequest;
use App\Http\Requests\RequestTrackRequest;
use App\Http\Resources\V1\QueueEntryResource;
use App\Http\Resources\V1\SearchHitResource;
use App\Models\Party;
use App\Models\PartyMember;
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

    private function member(Request $request, Party $party): PartyMember
    {
        $user = $request->user();
        assert($user instanceof User);

        return $party->memberFor($user) ?? throw RequestRefusedException::notAMember();
    }

    private function refusal(RequestRefusedException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], $exception->status());
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\ListPlayHistory;
use App\Domain\Queue\Actions\RatePlay;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\VoteDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListPlayHistoryRequest;
use App\Http\Requests\RatePlayRequest;
use App\Http\Resources\V1\PlayResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartyPlayController extends Controller
{
    public function history(ListPlayHistoryRequest $request, ListPlayHistory $listHistory, Party $party): AnonymousResourceCollection|JsonResponse
    {
        try {
            return PlayResource::collection($listHistory($party, $this->member($request, $party), $request->filters()));
        } catch (RequestRefusedException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }

    public function rate(RatePlayRequest $request, RatePlay $ratePlay, Party $party, Play $play): PlayResource|JsonResponse
    {
        return $this->apply($request, $ratePlay, $party, $play, $request->direction());
    }

    public function retract(Request $request, RatePlay $ratePlay, Party $party, Play $play): PlayResource|JsonResponse
    {
        return $this->apply($request, $ratePlay, $party, $play, null);
    }

    private function apply(Request $request, RatePlay $ratePlay, Party $party, Play $play, ?VoteDirection $direction): PlayResource|JsonResponse
    {
        abort_unless($play->party_id === $party->id, 404);

        try {
            return new PlayResource($ratePlay($this->member($request, $party), $play, $direction));
        } catch (RequestRefusedException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }

    private function member(Request $request, Party $party): PartyMember
    {
        $user = $request->user();
        assert($user instanceof User);

        return $party->memberFor($user) ?? throw RequestRefusedException::notAMember();
    }
}

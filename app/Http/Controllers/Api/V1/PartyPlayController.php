<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\ListPlayHistory;
use App\Domain\Queue\Actions\RatePlay;
use App\Domain\Queue\Actions\ShowSong;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\VoteDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListPlayHistoryRequest;
use App\Http\Requests\RatePlayRequest;
use App\Http\Resources\V1\HistoryEntryResource;
use App\Http\Resources\V1\PlayResource;
use App\Http\Resources\V1\SongDetailResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartyPlayController extends Controller
{
    public function history(ListPlayHistoryRequest $request, ListPlayHistory $listHistory, Party $party): AnonymousResourceCollection|JsonResponse
    {
        try {
            return HistoryEntryResource::collection($listHistory($party, $this->member($request, $party), $request->filters(), $request->filters()['per_page']));
        } catch (RequestRefusedException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }
    }

    public function show(Request $request, ShowSong $showSong, Party $party, Play $play): SongDetailResource|JsonResponse
    {
        try {
            return new SongDetailResource($showSong->play($this->currentUser($request), $party, $play));
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
}

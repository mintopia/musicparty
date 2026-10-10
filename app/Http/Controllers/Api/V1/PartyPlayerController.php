<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Playback\Actions\ChangePartyPlayer;
use App\Domain\Playback\Actions\IssuePlayerToken;
use App\Domain\Playback\Actions\ListPlayerTokens;
use App\Domain\Playback\Actions\RevokePlayerToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePartyPlayerRequest;
use App\Http\Requests\Api\V1\IssuePlayerTokenRequest;
use App\Http\Resources\V1\IssuedPlayerTokenResource;
use App\Http\Resources\V1\PartyPlayerResource;
use App\Http\Resources\V1\PlayerTokenResource;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PartyPlayerController extends Controller
{
    public function update(ChangePartyPlayerRequest $request, Party $party, ChangePartyPlayer $changePartyPlayer): PartyPlayerResource
    {
        try {
            $party = $changePartyPlayer(
                $this->actor(),
                $party,
                $request->validated('player_kind'),
                $request->validated('music_provider'),
            );
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return new PartyPlayerResource($party);
    }

    public function index(Request $request, Party $party, ListPlayerTokens $listPlayerTokens): AnonymousResourceCollection
    {
        try {
            $tokens = $listPlayerTokens($this->actor(), $party);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return PlayerTokenResource::collection($tokens);
    }

    public function store(IssuePlayerTokenRequest $request, Party $party, IssuePlayerToken $issuePlayerToken): JsonResponse
    {
        try {
            $token = $issuePlayerToken($this->actor(), $party, $request->validated('name'));
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return new IssuedPlayerTokenResource($token)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Party $party, int $token, RevokePlayerToken $revokePlayerToken): Response
    {
        try {
            $revokePlayerToken($this->actor(), $party, $token);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return response()->noContent();
    }

    private function actor(): User
    {
        $user = Auth::guard('sanctum')->user() ?? abort(401);

        return $user instanceof User ? $user : abort(403, 'Player Tokens cannot manage the Player.');
    }
}

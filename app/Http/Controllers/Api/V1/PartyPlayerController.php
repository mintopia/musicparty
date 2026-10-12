<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Actions\ChangePartyPlayer;
use App\Domain\Playback\Actions\CheckPlaybackNow;
use App\Domain\Playback\Actions\IssuePlayerToken;
use App\Domain\Playback\Actions\ListPlaybackDevices;
use App\Domain\Playback\Actions\ListPlayerTokens;
use App\Domain\Playback\Actions\RevokePlayerToken;
use App\Domain\Playback\Data\PlaybackDevice;
use App\Domain\Playback\Exceptions\PlaybackDevicesUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePartyPlayerRequest;
use App\Http\Requests\Api\V1\IssuePlayerTokenRequest;
use App\Http\Resources\V1\IssuedPlayerTokenResource;
use App\Http\Resources\V1\PartyPlayerResource;
use App\Http\Resources\V1\PlayerTokenResource;
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
                $request->deviceId(),
                $request->deviceName(),
                $request->deviceProvided(),
            );
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        }

        return new PartyPlayerResource($party);
    }

    /**
     * List the Host's Spotify devices.
     *
     * Host only. Returns 409 with `relink_required` when the Host's Spotify link needs re-linking to grant the playback scopes.
     */
    public function devices(Party $party, ListPlaybackDevices $listPlaybackDevices): JsonResponse
    {
        try {
            $devices = $listPlaybackDevices($this->actor(), $party);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        } catch (PlaybackDevicesUnavailableException $exception) {
            return response()->json(['relink_required' => true, 'message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (ProviderTemporaryFailure $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(['data' => array_map(fn (PlaybackDevice $device): array => $device->toArray(), $devices)]);
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

    public function poll(Party $party, CheckPlaybackNow $checkPlaybackNow): Response
    {
        if (! $checkPlaybackNow($party)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'The Party is not live with a Polling Player.');
        }

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    private function actor(): User
    {
        $user = Auth::guard('sanctum')->user() ?? abort(401);

        return $user instanceof User ? $user : abort(403, 'Player Tokens cannot manage the Player.');
    }
}

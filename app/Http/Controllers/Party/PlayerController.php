<?php

namespace App\Http\Controllers\Party;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Actions\ChangePartyPlayer;
use App\Domain\Playback\Actions\ListPlaybackDevices;
use App\Domain\Playback\Data\PlaybackDevice;
use App\Domain\Playback\Exceptions\PlaybackDevicesUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePartyPlayerRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlayerController extends Controller
{
    public function devices(Request $request, Party $party, ListPlaybackDevices $listPlaybackDevices): JsonResponse
    {
        try {
            $devices = $listPlaybackDevices($this->actor($request), $party);
        } catch (NotHostException $exception) {
            abort(403, $exception->getMessage());
        } catch (PlaybackDevicesUnavailableException $exception) {
            return response()->json(['relink_required' => true, 'message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (ProviderTemporaryFailure $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(['data' => array_map(fn (PlaybackDevice $device): array => $device->toArray(), $devices)]);
    }

    public function update(ChangePartyPlayerRequest $request, Party $party, ChangePartyPlayer $changePartyPlayer): RedirectResponse
    {
        try {
            $changePartyPlayer(
                $this->actor($request),
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

        return back()->with('success', 'Player saved');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}

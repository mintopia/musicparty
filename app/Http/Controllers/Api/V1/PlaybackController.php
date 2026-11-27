<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StartMode;
use App\Exceptions\PartyRelinkRequiredException;
use App\Http\Controllers\Api\V1\Concerns\RendersPlaybackErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StartPlaybackRequest;
use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\UpcomingSong;
use App\Services\Dashboard\PartyDashboard;
use App\Services\Music\MusicProviderFactory;
use App\Services\Playback\StartPlayback;
use App\Services\PlaybackControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class PlaybackController extends Controller
{
    use RendersPlaybackErrors;

    public function start(StartPlaybackRequest $request, Party $party, StartPlayback $playback): JsonResponse
    {
        $this->authorize('update', $party);

        try {
            $target = $playback->start($party, $request->input('deviceId'), StartMode::from($request->input('mode', 'start')));
        } catch (Throwable $e) {
            return $this->playbackError($e, $party);
        }

        return response()->json([
            'target' => ['kind' => $target->kind->value, 'trackId' => $target->track->id, 'positionMs' => $target->positionMs],
            'deviceId' => $target->deviceId,
        ]);
    }

    public function devices(Party $party, PlaybackControl $control, MusicProviderFactory $providers, PartyDashboard $dashboard): JsonResponse
    {
        $this->authorize('update', $party);

        try {
            if ($providers->partyNeedsRelink($party)) {
                throw new PartyRelinkRequiredException;
            }

            return response()->json(['data' => $dashboard->refreshDevices($party)]);
        } catch (Throwable $e) {
            return $this->playbackError($e, $party);
        }
    }

    public function activate(Party $party): Response
    {
        $this->authorize('update', $party);

        $party->forceFill(['active' => true])->save();
        $party->pushUpdate();
        PartyUpdate::dispatch($party);

        return response()->noContent();
    }

    public function playNow(Party $party, UpcomingSong $upcomingsong, StartPlayback $playback): Response|JsonResponse
    {
        $this->authorize('moderate', $party);
        $this->authorize('update', $upcomingsong);

        try {
            $playback->playNow($party, $upcomingsong);
        } catch (Throwable $e) {
            return $this->playbackError($e, $party);
        }

        return response()->noContent();
    }
}

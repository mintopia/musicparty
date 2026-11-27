<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RendersPlaybackErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartyControlRequest;
use App\Http\Resources\V1\PartyResource;
use App\Models\Party;
use App\Services\PlaybackControl;
use App\Services\PlaybackSync;
use Illuminate\Http\JsonResponse;
use Throwable;

class PartyController extends Controller
{
    use RendersPlaybackErrors;

    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
    }

    public function show(Party $party): PartyResource
    {
        return new PartyResource($party);
    }

    public function control(PartyControlRequest $request, Party $party, PlaybackControl $control, PlaybackSync $sync): PartyResource|JsonResponse
    {
        $this->authorize('moderate', $party);

        try {
            $control->perform($party, (string) $request->input('action'), $request->deviceId ?? null, $request->has('positionMs') ? $request->integer('positionMs') : null);
        } catch (Throwable $e) {
            return $this->playbackError($e, $party);
        }

        $sync->sync($party);

        return new PartyResource($party);
    }
}

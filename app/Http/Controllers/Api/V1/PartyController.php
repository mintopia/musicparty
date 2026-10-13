<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NoActiveDeviceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartyControlRequest;
use App\Http\Resources\V1\PartyResource;
use App\Models\Party;
use App\Services\PlaybackControl;
use App\Services\PlaybackSync;

class PartyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
    }

    public function show(Party $party): PartyResource
    {
        return new PartyResource($party);
    }

    public function control(PartyControlRequest $request, Party $party, PlaybackControl $control, PlaybackSync $sync): PartyResource
    {
        $this->authorize('update', $party);

        try {
            $control->perform($party, (string) $request->input('action'), $request->deviceId ?? null);
        } catch (NoActiveDeviceException $ex) {
            abort(409, 'No active playback device. Open Spotify on a device or pick one and try again.');
        }

        $sync->sync($party);

        return new PartyResource($party);
    }
}

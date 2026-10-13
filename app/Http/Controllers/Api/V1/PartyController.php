<?php

namespace App\Http\Controllers\Api\V1;

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
        $control->perform($party, (string) $request->input('action'), $request->deviceId ?? null);

        $sync->sync($party);

        return new PartyResource($party);
    }
}

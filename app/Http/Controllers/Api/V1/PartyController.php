<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartyControlRequest;
use App\Http\Resources\V1\PartyResource;
use App\Models\Party;
use InvalidArgumentException;

class PartyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
    }

    public function show(Party $party)
    {
        return new PartyResource($party);
    }

    public function control(PartyControlRequest $request, Party $party)
    {
        $this->authorize('update', $party);
        match ($request->input('action')) {
            'play' => $party->play($request->deviceId ?? null),
            'pause' => $party->pause(),
            'next' => $party->nextTrack(),
            'previous' => $party->previousTrack(),
            default => throw new InvalidArgumentException('Unsupported party control action.'),
        };

        $party->updateState();

        return new PartyResource($party);
    }
}

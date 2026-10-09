<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Party\Actions\CreateParty;
use App\Domain\Party\Actions\JoinParty;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartyControlRequest;
use App\Http\Requests\StorePartyRequest;
use App\Http\Resources\V1\PartyResource;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Party::class, 'party');
    }

    public function store(StorePartyRequest $request, CreateParty $createParty): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $party = $createParty(
            $user,
            $request->string('name')->trim()->toString(),
            $request->string('music_provider')->toString(),
            $request->string('player_kind')->toString(),
        );

        return new PartyResource($party)->response()->setStatusCode(201);
    }

    public function join(Request $request, JoinParty $joinParty, Party $party): PartyResource
    {
        $user = $request->user();
        assert($user instanceof User);

        $joinParty($user, $party);

        return new PartyResource($party);
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
        };

        $party->updateState();

        return new PartyResource($party);
    }
}

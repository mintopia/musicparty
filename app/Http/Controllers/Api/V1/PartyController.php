<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Party\Actions\CreateParty;
use App\Domain\Party\Actions\JoinParty;
use App\Domain\Party\Actions\ListPartyLog;
use App\Domain\Party\Actions\UpdatePartySettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartyControlRequest;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Http\Resources\V1\PartyLogEntryResource;
use App\Http\Resources\V1\PartyResource;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function update(UpdatePartyRequest $request, UpdatePartySettings $updateSettings, Party $party): PartyResource
    {
        $user = $request->user();
        assert($user instanceof User);

        /** @var array{name?: string, downvotes?: bool, downvotes_per_hour?: int|null} $settings */
        $settings = $request->safe()->only(['name', 'downvotes', 'downvotes_per_hour']);

        return new PartyResource($updateSettings($user, $party, $settings));
    }

    public function log(ListPartyLog $listLog, Party $party): AnonymousResourceCollection
    {
        $this->authorize('viewLog', $party);

        return PartyLogEntryResource::collection($listLog($party));
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

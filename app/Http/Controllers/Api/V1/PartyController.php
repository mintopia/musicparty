<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Actions\JoinParty;
use App\Domain\Party\Actions\CreateParty;
use App\Domain\Party\Actions\EndParty;
use App\Domain\Party\Actions\GoLiveParty;
use App\Domain\Party\Actions\ListPartyLog;
use App\Domain\Party\Actions\PauseParty;
use App\Domain\Party\Actions\ReopenParty;
use App\Domain\Party\Actions\UpdatePartySettings;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Actions\ControlPlayback;
use App\Domain\Playback\Exceptions\PlaybackControlRefusedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPlaybackRequest;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Http\Resources\V1\PartyLogEntryResource;
use App\Http\Resources\V1\PartyResource;
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

    public function show(Party $party): PartyResource
    {
        return new PartyResource($party);
    }

    public function update(UpdatePartyRequest $request, UpdatePartySettings $updateSettings, Party $party): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $settings = $request->safe()->only(['name', 'fallback_playlist_id', 'allow_requests', 'max_requests', 'explicit', 'min_song_length', 'max_song_length', 'no_repeat_interval', 'hold_requests', 'downvotes', 'downvotes_per_hour', 'selection_mode']);
        $result = $updateSettings($user, $party, $settings);

        $response = new PartyResource($result['party']);
        $warning = $result['warning'];

        if ($warning !== null) {
            $response->additional(['meta' => ['warnings' => [$warning->message()]]]);
        }

        return $response->response();
    }

    public function live(Request $request, GoLiveParty $goLive, Party $party): PartyResource
    {
        $this->authorize('transition', $party);

        return new PartyResource($goLive($this->currentUser($request), $party));
    }

    public function pause(Request $request, PauseParty $pauseParty, Party $party): PartyResource
    {
        $this->authorize('transition', $party);

        return new PartyResource($pauseParty($this->currentUser($request), $party));
    }

    public function end(Request $request, EndParty $endParty, Party $party): PartyResource
    {
        $this->authorize('transition', $party);

        return new PartyResource($endParty($this->currentUser($request), $party));
    }

    public function reopen(Request $request, ReopenParty $reopenParty, Party $party): PartyResource
    {
        $this->authorize('transition', $party);

        return new PartyResource($reopenParty($this->currentUser($request), $party));
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }

    public function log(ListPartyLog $listLog, Party $party): AnonymousResourceCollection
    {
        $this->authorize('viewLog', $party);

        return PartyLogEntryResource::collection($listLog($party));
    }

    public function playback(ControlPlaybackRequest $request, ControlPlayback $controlPlayback, Party $party): JsonResponse
    {
        try {
            $controlPlayback($party, $request->control(), $request->value());
        } catch (PlaybackControlRefusedException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }

        return response()->json(['data' => ['control' => $request->control()->value, 'value' => $request->value()]]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Domain\Party\Actions\CreateParty;
use App\Domain\Party\Actions\JoinParty;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Actions\ListQueue;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Actions\SearchPartyProvider;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Http\Requests\JoinPartyRequest;
use App\Http\Requests\RequestTrackRequest;
use App\Http\Requests\StorePartyRequest;
use App\Http\Resources\V1\QueueEntryResource;
use App\Http\Resources\V1\SearchHitResource;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function create(Request $request, PairingCatalogue $catalogue): Response
    {
        $this->authorize('create', Party::class);

        return Inertia::render('Party/Create', [
            'providers' => $catalogue->providers(),
            'players' => $catalogue->players(),
        ]);
    }

    public function store(StorePartyRequest $request, CreateParty $createParty): RedirectResponse
    {
        $party = $createParty(
            $this->currentUser($request),
            $request->string('name')->trim()->toString(),
            $request->string('music_provider')->toString(),
            $request->string('player_kind')->toString(),
        );

        return redirect()->route('parties.show', ['party' => $party->code])->with('successMessage', 'Party created');
    }

    public function join(JoinPartyRequest $request, JoinParty $joinParty): RedirectResponse
    {
        $party = Party::findByCode($request->string('code')->toString());

        if ($party === null) {
            return back()->withErrors(['code' => 'No party found with that code.']);
        }

        $joinParty($this->currentUser($request), $party);

        return redirect()->route('parties.show', ['party' => $party->code]);
    }

    public function show(
        Request $request,
        JoinParty $joinParty,
        ListQueue $listQueue,
        SearchPartyProvider $search,
        Party $party,
        string $section = 'queue',
    ): Response {
        $member = $joinParty($this->currentUser($request), $party);
        $query = trim($request->string('q')->toString());
        $results = null;
        $searchError = null;

        if ($query !== '') {
            try {
                $results = SearchHitResource::collection($search($party, $query))->resolve($request);
            } catch (RequestRefusedException $exception) {
                $results = [];
                $searchError = $exception->getMessage();
            }
        }

        return Inertia::render('Party/Show', [
            'party' => [
                'code' => $party->code,
                'name' => $party->name,
                'state' => $party->state->value,
                'musicProvider' => $party->music_provider,
                'playerKind' => $party->player_kind,
            ],
            'membership' => [
                'role' => $member->role->value,
                'banned' => $member->banned,
            ],
            'section' => $section,
            'readOnly' => $party->state === PartyState::Ended || $member->banned,
            'nowPlaying' => null,
            'queue' => QueueEntryResource::collection($listQueue($party, $member))->resolve($request),
            'search_query' => $query,
            'results' => $results,
            'search_error' => $searchError,
        ]);
    }

    public function storeRequest(RequestTrackRequest $request, RequestTrack $requestTrack, Party $party): RedirectResponse
    {
        $member = $party->memberFor($this->currentUser($request));

        try {
            $outcome = $requestTrack(
                $party,
                $member ?? throw RequestRefusedException::notAMember(),
                $request->string('provider_track_id')->toString(),
            );
        } catch (RequestRefusedException $exception) {
            return back()->withErrors(['request' => $exception->getMessage()]);
        }

        $message = $outcome->created ? 'Track requested' : 'Already in the queue, your vote was added';

        return back()->with('success', $message)->with('successMessage', $message);
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}

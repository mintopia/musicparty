<?php

namespace App\Http\Controllers;

use App\Domain\Party\Actions\CreateParty;
use App\Domain\Party\Actions\JoinParty;
use App\Domain\Party\Actions\ListPartyLog;
use App\Domain\Party\Actions\UpdatePartySettings;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Http\Requests\JoinPartyRequest;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Http\Resources\V1\PartyLogEntryResource;
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

    public function show(Request $request, JoinParty $joinParty, Party $party, string $section = 'queue'): Response
    {
        $member = $joinParty($this->currentUser($request), $party);

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
        ]);
    }

    public function update(UpdatePartyRequest $request, UpdatePartySettings $updateSettings, Party $party): RedirectResponse
    {
        $this->authorize('update', $party);

        /** @var array{name?: string} $settings */
        $settings = $request->safe()->only(['name']);
        $updateSettings($this->currentUser($request), $party, $settings);

        return back()->with('successMessage', 'Settings saved');
    }

    public function log(Request $request, ListPartyLog $listLog, Party $party): Response
    {
        $this->authorize('viewLog', $party);

        return Inertia::render('Party/Log', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'entries' => PartyLogEntryResource::collection($listLog($party)),
        ]);
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}

<?php

namespace App\Http\Controllers;

use App\Domain\Party\Actions\CreateParty;
use App\Domain\Party\Actions\EndParty;
use App\Domain\Party\Actions\GoLiveParty;
use App\Domain\Party\Actions\JoinParty;
use App\Domain\Party\Actions\ListPartyLog;
use App\Domain\Party\Actions\PauseParty;
use App\Domain\Party\Actions\ReopenParty;
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
            'canManage' => $party->canBeManagedBy($this->currentUser($request)),
            'readOnly' => $party->state === PartyState::Ended || $member->banned,
            'nowPlaying' => null,
        ]);
    }

    public function update(UpdatePartyRequest $request, UpdatePartySettings $updateSettings, Party $party): RedirectResponse
    {
        $this->authorize('update', $party);

        $settings = $request->safe()->only(['name', 'fallback_playlist_id', 'explicit', 'min_song_length', 'max_song_length', 'no_repeat_interval']);
        $result = $updateSettings($this->currentUser($request), $party, $settings);

        $redirect = back()->with('successMessage', 'Settings saved');
        $warning = $result['warning'];

        return $warning === null ? $redirect : $redirect->with('warningMessage', $warning->message());
    }

    public function live(Request $request, GoLiveParty $goLive, Party $party): RedirectResponse
    {
        $this->authorize('transition', $party);
        $goLive($this->currentUser($request), $party);

        return back()->with('successMessage', 'Party is live');
    }

    public function pause(Request $request, PauseParty $pauseParty, Party $party): RedirectResponse
    {
        $this->authorize('transition', $party);
        $pauseParty($this->currentUser($request), $party);

        return back()->with('successMessage', 'Party paused');
    }

    public function end(Request $request, EndParty $endParty, Party $party): RedirectResponse
    {
        $this->authorize('transition', $party);
        $endParty($this->currentUser($request), $party);

        return back()->with('successMessage', 'Party ended');
    }

    public function reopen(Request $request, ReopenParty $reopenParty, Party $party): RedirectResponse
    {
        $this->authorize('transition', $party);
        $reopenParty($this->currentUser($request), $party);

        return back()->with('successMessage', 'Party reopened');
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

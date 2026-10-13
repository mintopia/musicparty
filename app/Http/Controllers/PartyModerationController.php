<?php

namespace App\Http\Controllers;

use App\Enums\PartyModerationType;
use App\Http\Requests\PartyModerationRequest;
use App\Models\Party;
use App\Models\PartyModeration;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartyModerationController extends Controller
{
    public function index(Request $request, Party $party): View
    {
        $query = $party->moderations();
        $params = [
            'order' => 'id',
            'order_direction' => 'asc',
        ];
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }

        $moderations = $query->paginate($perPage)->appends($params);

        return view('partymoderation.index', [
            'party' => $party,
            'canManage' => true,
            'moderations' => $moderations,
            'filters' => (object) $params,
            'params' => $params,
        ]);
    }

    public function create(Party $party, Request $request): View
    {
        $spotifyData = $request->has('track')
            ? app(SpotifyGateway::class)->findTrack($party->user, (string) $request->input('track'))
            : null;

        $moderation = new PartyModeration;
        $moderation->party()->associate($party);

        return view('partymoderation.create', [
            'party' => $party,
            'canManage' => true,
            'types' => PartyModerationType::getHumanReadableTypes(),
            'spotifyData' => $spotifyData,
            'moderation' => $moderation,
        ]);
    }

    public function store(PartyModerationRequest $request, Party $party): RedirectResponse
    {
        $moderation = new PartyModeration;
        $moderation->party()->associate($party);
        $this->updateObject($moderation, $request);

        return response()
            ->redirectToRoute('parties.moderation.index', $party->code)
            ->with('successMessage', 'The filter has been created');
    }

    public function edit(Party $party, PartyModeration $moderation): View
    {
        return view('partymoderation.edit', [
            'party' => $party,
            'canManage' => true,
            'types' => PartyModerationType::getHumanReadableTypes(),
            'moderation' => $moderation,
        ]);
    }

    public function delete(Party $party, PartyModeration $moderation): View
    {
        return view('partymoderation.delete', [
            'party' => $party,
            'canManage' => true,
            'types' => PartyModerationType::getHumanReadableTypes(),
            'moderation' => $moderation,
        ]);
    }

    public function update(PartyModerationRequest $request, Party $party, PartyModeration $moderation): RedirectResponse
    {
        $this->updateObject($moderation, $request);

        return redirect()
            ->route('parties.moderation.index', $party->code)
            ->with('successMessage', 'The filter has been updated');
    }

    protected function updateObject(PartyModeration $moderation, PartyModerationRequest $request): void
    {
        $moderation->enabled = (bool) $request->input('enabled');
        $moderation->regex = (bool) $request->input('regex');
        foreach (PartyModerationType::cases() as $type) {
            if ($type->name === $request->input('type')) {
                $moderation->type = $type;
                break;
            }
        }
        $moderation->value = $request->input('value');
        $moderation->notes = $request->input('notes');
        $moderation->save();
    }

    public function destroy(Party $party, PartyModeration $moderation): RedirectResponse
    {
        $moderation->delete();

        return response()
            ->redirectToRoute('parties.moderation.index', $party->code)
            ->with('successMessage', 'The filter has been deleted');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\PartyModerationAction;
use App\Enums\PartyModerationType;
use App\Enums\Purpose;
use App\Http\Requests\PartyModerationRequest;
use App\Models\Party;
use App\Models\PartyModeration;
use App\Services\Music\MusicProviderFactory;
use App\Services\Requests\ModerationTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyModerationController extends Controller
{
    public function index(Request $request, Party $party): Response
    {
        $params = [
            'order' => 'id',
            'order_direction' => 'asc',
        ];
        $perPage = $request->input('per_page', 20);
        if ($perPage !== 20) {
            $params['per_page'] = $perPage;
        }

        $moderations = $party->moderations()->paginate($perPage)->appends($params);
        $moderations->through(fn (PartyModeration $row): array => $this->present($row));

        return Inertia::render('Parties/Moderation/Index', [
            'party' => $this->partyProps($party),
            'playerState' => $party->getState(),
            'moderations' => $moderations,
            'moderationTabs' => app(ModerationTabs::class)->for($party),
        ]);
    }

    public function create(Party $party, Request $request): Response
    {
        $track = $request->has('track')
            ? app(MusicProviderFactory::class)->forParty($party, Purpose::Control)->findTrack((string) $request->input('track'))
            : null;

        $moderation = new PartyModeration;
        $moderation->party()->associate($party);

        return Inertia::render('Parties/Moderation/Create', [
            'party' => $this->partyProps($party),
            'playerState' => $party->getState(),
            'types' => PartyModerationType::getHumanReadableTypes(),
            'actions' => PartyModerationAction::getHumanReadableActions(),
            'moderation' => $this->present($moderation),
            'track' => $track === null ? null : [
                'id' => $track->id,
                'name' => $track->name,
                'isrc' => $track->isrc,
                'album' => ['id' => $track->album->id, 'name' => $track->album->name],
                'artists' => array_map(fn ($artist): array => ['id' => $artist->id, 'name' => $artist->name], $track->artists),
            ],
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

    public function edit(Party $party, PartyModeration $moderation): Response
    {
        return Inertia::render('Parties/Moderation/Edit', [
            'party' => $this->partyProps($party),
            'playerState' => $party->getState(),
            'types' => PartyModerationType::getHumanReadableTypes(),
            'actions' => PartyModerationAction::getHumanReadableActions(),
            'moderation' => $this->present($moderation),
        ]);
    }

    public function delete(Party $party, PartyModeration $moderation): Response
    {
        return Inertia::render('Parties/Moderation/Delete', [
            'party' => $this->partyProps($party),
            'playerState' => $party->getState(),
            'moderation' => $this->present($moderation),
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
        $moderation->action = PartyModerationAction::tryFrom((string) $request->input('action')) ?? PartyModerationAction::Reject;
        $moderation->value = $request->input('value');
        $moderation->notes = $request->input('notes');
        $moderation->save();
    }

    /**
     * @return array{code: string, name: string, canManage: bool}
     */
    protected function partyProps(Party $party): array
    {
        return ['code' => $party->code, 'name' => $party->name, 'canManage' => true];
    }

    /**
     * @return array{id: int|null, enabled: bool, regex: bool, type: string|null, type_name: string|null, action: string, value: string|null, notes: string|null}
     */
    protected function present(PartyModeration $moderation): array
    {
        $type = $moderation->getAttribute('type');

        return [
            'id' => $moderation->id,
            'enabled' => (bool) $moderation->enabled,
            'regex' => (bool) $moderation->regex,
            'type' => $type instanceof PartyModerationType ? $type->name : null,
            'type_name' => $type instanceof PartyModerationType ? $type->getHumanReadableName() : null,
            'action' => $moderation->action->value,
            'value' => $moderation->value,
            'notes' => $moderation->notes,
        ];
    }

    public function destroy(Party $party, PartyModeration $moderation): RedirectResponse
    {
        $moderation->delete();

        return response()
            ->redirectToRoute('parties.moderation.index', $party->code)
            ->with('successMessage', 'The filter has been deleted');
    }
}

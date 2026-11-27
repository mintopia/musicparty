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
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'in:on,off'],
            'action' => ['nullable', 'in:'.implode(',', array_column(PartyModerationAction::cases(), 'value'))],
            'pattern' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $search = trim($filters['q'] ?? '');
        $state = $filters['state'] ?? null;
        $action = $filters['action'] ?? null;
        $pattern = (bool) ($filters['pattern'] ?? false);

        $moderations = $party->moderations()
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('value', 'like', $like)->orWhere('notes', 'like', $like));
            })
            ->when($state !== null, fn ($query) => $query->where('enabled', $state === 'on'))
            ->when($action !== null, fn ($query) => $query->where('action', $action))
            ->when($pattern, fn ($query) => $query->where('regex', true))
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
        $moderations->through(fn (PartyModeration $row): array => $this->present($row));

        $counts = $party->moderations()->toBase()
            ->selectRaw('count(*) as total, sum(enabled = 1) as enabled, sum(action = ?) as flag, sum(action = ?) as reject, sum(regex = 1) as pattern', [PartyModerationAction::Flag->value, PartyModerationAction::Reject->value])
            ->first();

        return Inertia::render('Parties/Moderation/Index', [
            'party' => $this->partyProps($party),
            'playerState' => $party->getState(),
            'moderations' => $moderations,
            'filters' => ['q' => $search, 'state' => $state ?? '', 'action' => $action ?? '', 'pattern' => $pattern],
            'counts' => [
                'total' => (int) ($counts->total ?? 0),
                'on' => (int) ($counts->enabled ?? 0),
                'off' => (int) ($counts->total ?? 0) - (int) ($counts->enabled ?? 0),
                'flag' => (int) ($counts->flag ?? 0),
                'reject' => (int) ($counts->reject ?? 0),
                'pattern' => (int) ($counts->pattern ?? 0),
            ],
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
     * @return array{code: string, name: string, canManage: bool, canModerate: bool, ownerName: string}
     */
    protected function partyProps(Party $party): array
    {
        $canManage = request()->user()?->can('update', $party) ?? false;

        return ['code' => $party->code, 'name' => $party->name, 'canManage' => $canManage, 'canModerate' => true, 'ownerName' => $party->user->nickname];
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

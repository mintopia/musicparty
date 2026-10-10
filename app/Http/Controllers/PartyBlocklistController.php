<?php

namespace App\Http\Controllers;

use App\Domain\Party\Actions\AddBlocklistEntry;
use App\Domain\Party\Actions\ListBlocklistEntries;
use App\Domain\Party\Actions\RemoveBlocklistEntry;
use App\Domain\Party\Actions\UpdateBlocklistEntry;
use App\Domain\Party\FallbackPlaylistCheck;
use App\Domain\Queue\BlocklistMatchType;
use App\Http\Requests\Api\V1\StoreBlocklistEntryRequest;
use App\Http\Requests\Api\V1\UpdateBlocklistEntryRequest;
use App\Http\Resources\V1\BlocklistEntryResource;
use App\Models\BlocklistEntry;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyBlocklistController extends Controller
{
    public function index(Request $request, ListBlocklistEntries $listEntries, Party $party): Response
    {
        $this->authorize('moderate', $party);

        return Inertia::render('Party/Blocklist', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'entries' => BlocklistEntryResource::collection($listEntries($party))->resolve($request),
            'matchTypes' => array_map(fn (BlocklistMatchType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'supportsRegex' => $type->supportsRegex(),
            ], BlocklistMatchType::cases()),
            'abilities' => [
                'canManage' => $request->user()?->can('moderate', $party) ?? false,
            ],
        ]);
    }

    public function store(StoreBlocklistEntryRequest $request, AddBlocklistEntry $addEntry, Party $party): RedirectResponse
    {
        $this->authorize('moderate', $party);

        ['entry' => $entry, 'warning' => $warning] = $addEntry(
            $this->currentUser($request),
            $party,
            $request->matchType(),
            $request->string('value')->toString(),
            $request->isRegex(),
            $request->isEnabled(),
            $request->notes(),
        );

        return $this->withWarning(back()->with('successMessage', "{$entry->value} was added to the blocklist"), $warning);
    }

    public function update(UpdateBlocklistEntryRequest $request, UpdateBlocklistEntry $updateEntry, Party $party, BlocklistEntry $entry): RedirectResponse
    {
        $this->authorize('moderate', $party);
        abort_unless($entry->party_id === $party->id, 404);

        ['entry' => $updated, 'warning' => $warning] = $updateEntry(
            $this->currentUser($request),
            $party,
            $entry,
            $request->matchType(),
            $request->string('value')->toString(),
            $request->isRegex(),
            $request->isEnabled(),
            $request->notes(),
        );

        return $this->withWarning(back()->with('successMessage', "{$updated->value} was updated"), $warning);
    }

    public function destroy(Request $request, RemoveBlocklistEntry $removeEntry, Party $party, BlocklistEntry $entry): RedirectResponse
    {
        $this->authorize('moderate', $party);
        abort_unless($entry->party_id === $party->id, 404);

        $warning = $removeEntry($this->currentUser($request), $party, $entry);

        return $this->withWarning(back()->with('successMessage', "{$entry->value} was removed from the blocklist"), $warning);
    }

    private function withWarning(RedirectResponse $redirect, ?FallbackPlaylistCheck $warning): RedirectResponse
    {
        return $warning === null ? $redirect : $redirect->with('warningMessage', $warning->message());
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}

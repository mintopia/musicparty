<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Actions\AddBlocklistEntry;
use App\Domain\Party\Actions\ListBlocklistEntries;
use App\Domain\Party\Actions\RemoveBlocklistEntry;
use App\Domain\Party\Actions\UpdateBlocklistEntry;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBlocklistEntryRequest;
use App\Http\Requests\Api\V1\UpdateBlocklistEntryRequest;
use App\Http\Resources\V1\BlocklistEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyBlocklistController extends Controller
{
    public function index(ListBlocklistEntries $listEntries, Party $party): AnonymousResourceCollection
    {
        $this->authorize('moderate', $party);

        return BlocklistEntryResource::collection($listEntries($party));
    }

    public function store(StoreBlocklistEntryRequest $request, AddBlocklistEntry $addEntry, Party $party): JsonResponse
    {
        $this->authorize('moderate', $party);

        ['entry' => $entry] = $addEntry(
            $this->currentUser($request),
            $party,
            $request->matchType(),
            $request->string('value')->toString(),
            $request->isRegex(),
            $request->isEnabled(),
            $request->notes(),
        );

        return new BlocklistEntryResource($entry)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateBlocklistEntryRequest $request, UpdateBlocklistEntry $updateEntry, Party $party, BlocklistEntry $entry): BlocklistEntryResource
    {
        $this->authorize('moderate', $party);
        abort_unless($entry->party_id === $party->id, 404);

        return new BlocklistEntryResource($updateEntry(
            $this->currentUser($request),
            $party,
            $entry,
            $request->matchType(),
            $request->string('value')->toString(),
            $request->isRegex(),
            $request->isEnabled(),
            $request->notes(),
        )['entry']);
    }

    public function destroy(Request $request, RemoveBlocklistEntry $removeEntry, Party $party, BlocklistEntry $entry): Response
    {
        $this->authorize('moderate', $party);
        abort_unless($entry->party_id === $party->id, 404);

        $removeEntry($this->currentUser($request), $party, $entry);

        return response()->noContent();
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}

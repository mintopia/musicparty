<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePartyRequest;
use App\Http\Requests\Api\V1\UpdatePartyRequest;
use App\Models\Party;
use App\Transformers\Api\V1\PartyTransformer;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        $items = [];
        if ($request->user()->party) {
            $items = [$request->user()->party];
        }
        $parties = new LengthAwarePaginator($items, count($items), 20, 1);
        return fractal($parties, new PartyTransformer($request))->respond();
    }

    public function store(StorePartyRequest $request)
    {
        $party = new Party();
        $party->user()->associate($request->user());
        $this->updateParty($request, $party);
        $party->save();
        return fractal($party, new PartyTransformer($request))->respond(201);
    }

    public function show(Request $request, Party $party)
    {
        return fractal($party, new PartyTransformer($request))->respond();
    }

    public function update(UpdatePartyRequest $request, Party $party)
    {
        $this->updateParty($party, $request);
        $party->save();
        return fractal($party, new PartyTransformer($request))->respond();
    }

    public function destroy(Party $party)
    {
        $party->delete();
        return response()->noContent();
    }

    protected function updateParty(Request $request, Party $party): Party
    {
        $party->name = $request->input('name', $party->name);
        $party->backup_playlist_id = $request->input('backup_playlist_id', $party->backup_playlist_id);
        return $party;
    }
}

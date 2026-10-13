<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\ModSettingsStore;
use App\Services\ModSettingsUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyModsController extends Controller
{
    public function edit(Party $party, ModSettingsStore $store): Response
    {
        $this->authorize('update', $party);

        return Inertia::render('Parties/Mods', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'mods' => $store->presentFor($party),
        ]);
    }

    public function update(Request $request, Party $party, ModSettingsUpdater $updater): RedirectResponse
    {
        $this->authorize('update', $party);
        $validated = $request->validate(['mods' => ['present', 'array']]);

        $updater->updateParty($party, $validated['mods']);

        return redirect()->route('parties.mods.edit', $party->code);
    }
}

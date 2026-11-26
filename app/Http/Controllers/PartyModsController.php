<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\ModSettingsUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartyModsController extends Controller
{
    public function edit(Party $party): RedirectResponse
    {
        $this->authorize('update', $party);

        return redirect()->route('parties.edit', ['party' => $party->code, 'tab' => 'mods']);
    }

    public function updateMod(Request $request, Party $party, string $mod, ModSettingsUpdater $updater): RedirectResponse
    {
        $this->authorize('update', $party);
        $validated = $request->validate(['enabled' => ['sometimes', 'boolean'], 'settings' => ['present', 'array']]);

        $updater->updateParty($party, [$mod => $validated]);

        return redirect()->to(url()->previous(route('parties.edit', ['party' => $party->code, 'tab' => 'mods'])));
    }
}

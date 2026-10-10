<?php

namespace App\Http\Controllers;

use App\Domain\Mod\Actions\DisableMod;
use App\Domain\Mod\Actions\EnableMod;
use App\Domain\Mod\Actions\ListMods;
use App\Domain\Mod\Actions\UpdateModSettings;
use App\Http\Requests\Api\V1\UpdateModSettingsRequest;
use App\Http\Resources\V1\ModResource;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyModController extends Controller
{
    public function index(Request $request, ListMods $listMods, Party $party): Response
    {
        $this->authorize('manageMods', $party);

        return Inertia::render('Party/Mods', [
            'party' => ['code' => $party->code, 'name' => $party->name],
            'mods' => ModResource::collection($listMods($party))->resolve($request),
        ]);
    }

    public function enable(Request $request, EnableMod $enableMod, Party $party, string $mod): RedirectResponse
    {
        $this->authorize('manageMods', $party);

        $enableMod($this->currentUser($request), $party, $mod);

        return back()->with('successMessage', 'Mod enabled');
    }

    public function disable(Request $request, DisableMod $disableMod, Party $party, string $mod): RedirectResponse
    {
        $this->authorize('manageMods', $party);

        $disableMod($this->currentUser($request), $party, $mod);

        return back()->with('successMessage', 'Mod disabled');
    }

    public function update(UpdateModSettingsRequest $request, UpdateModSettings $updateSettings, Party $party, string $mod): RedirectResponse
    {
        $this->authorize('manageMods', $party);

        $updateSettings($this->currentUser($request), $party, $mod, $request->settings());

        return back()->with('successMessage', 'Mod settings saved');
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Mod\Actions\DisableMod;
use App\Domain\Mod\Actions\EnableMod;
use App\Domain\Mod\Actions\ListMods;
use App\Domain\Mod\Actions\UpdateModSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateModSettingsRequest;
use App\Http\Resources\V1\ModResource;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyModController extends Controller
{
    public function index(ListMods $listMods, Party $party): AnonymousResourceCollection
    {
        $this->authorize('manageMods', $party);

        return ModResource::collection($listMods($party));
    }

    public function enable(Request $request, EnableMod $enableMod, ListMods $listMods, Party $party, string $mod): ModResource
    {
        $this->authorize('manageMods', $party);

        $enableMod($this->currentUser($request), $party, $mod);

        return $this->resourceFor($listMods, $party, $mod);
    }

    public function disable(Request $request, DisableMod $disableMod, Party $party, string $mod): Response
    {
        $this->authorize('manageMods', $party);

        $disableMod($this->currentUser($request), $party, $mod);

        return response()->noContent();
    }

    public function update(UpdateModSettingsRequest $request, UpdateModSettings $updateSettings, ListMods $listMods, Party $party, string $mod): ModResource
    {
        $this->authorize('manageMods', $party);

        $updateSettings($this->currentUser($request), $party, $mod, $request->settings());

        return $this->resourceFor($listMods, $party, $mod);
    }

    private function resourceFor(ListMods $listMods, Party $party, string $modId): ModResource
    {
        foreach ($listMods($party) as $status) {
            if ($status->mod->id() === $modId) {
                return new ModResource($status);
            }
        }

        abort(404);
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}

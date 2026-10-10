<?php

namespace App\Http\Controllers;

use App\Domain\Mod\EnabledMods;
use App\Domain\Party\Actions\ShowPartyTv;
use App\Models\Party;
use Inertia\Inertia;
use Inertia\Response;

class PartyTvController extends Controller
{
    public function show(ShowPartyTv $showPartyTv, EnabledMods $enabledMods, Party $party): Response
    {
        return Inertia::render('Party/Tv', [...$showPartyTv($party), 'enabled_mods' => $enabledMods->idsFor($party)]);
    }
}

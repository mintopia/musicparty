<?php

namespace App\Http\Controllers;

use App\Domain\Party\Actions\ShowPartyTv;
use App\Models\Party;
use Inertia\Inertia;
use Inertia\Response;

class PartyTvController extends Controller
{
    public function show(ShowPartyTv $showPartyTv, Party $party): Response
    {
        return Inertia::render('Party/Tv', $showPartyTv($party));
    }
}

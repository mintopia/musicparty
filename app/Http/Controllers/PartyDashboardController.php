<?php

namespace App\Http\Controllers;

use App\Models\Party;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyDashboardController extends Controller
{
    public function show(Request $request, Party $party): Response
    {
        return Inertia::render('Parties/Dashboard', ['party' => ['code' => $party->code]]);
    }
}

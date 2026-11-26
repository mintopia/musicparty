<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\Dashboard\PartyDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyDashboardController extends Controller
{
    public function show(Request $request, Party $party, PartyDashboard $dashboard): Response
    {
        return Inertia::render('Parties/Dashboard', $dashboard->props($party, $this->resolveAuthenticatedUser($request)));
    }
}

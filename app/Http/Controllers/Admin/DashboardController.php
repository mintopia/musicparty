<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => [
                    'total' => User::query()->count(),
                    'lastWeek' => User::query()->where('created_at', '>=', now()->subWeek())->count(),
                ],
                'parties' => [
                    'total' => Party::query()->count(),
                    'live' => Party::query()->where('state', PartyState::Live)->count(),
                ],
            ],
        ]);
    }
}

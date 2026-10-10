<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\EnterActAsHost;
use App\Domain\Admin\Actions\LeaveActAsHost;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AdminPartyResource;
use App\Models\Party;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function index(Request $request): Response
    {
        $admin = $request->user() ?? throw new AuthenticationException;

        $parties = Party::query()
            ->withExists(['adminHostSessions as acting_as_host' => fn ($query) => $query->where('user_id', $admin->id)])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/Parties/Index', [
            'parties' => AdminPartyResource::collection($parties)->resolve($request),
        ]);
    }

    public function enter(Request $request, Party $party, EnterActAsHost $enterActAsHost): RedirectResponse
    {
        $enterActAsHost->handle($request->user() ?? throw new AuthenticationException, $party);

        return back();
    }

    public function leave(Request $request, Party $party, LeaveActAsHost $leaveActAsHost): RedirectResponse
    {
        $leaveActAsHost->handle($request->user() ?? throw new AuthenticationException, $party);

        return back();
    }
}

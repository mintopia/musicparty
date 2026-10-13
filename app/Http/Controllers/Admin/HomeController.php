<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => [
                    'total' => User::count(),
                    'lastWeek' => User::where('created_at', '>', Carbon::now()->subWeek())->count(),
                ],
            ],
        ]);
    }

    public function unimpersonate(Request $request): RedirectResponse
    {
        if (! $request->session()->get('impersonating')) {
            abort(403);
        }
        $impersonated = $this->resolveAuthenticatedUser($request);
        $user = User::findOrFail((int) $request->session()->get('originalUserId'));
        $request->session()->flush();
        $request->session()->regenerate(true);

        auth('web')->login($user);

        return response()->redirectToRoute('admin.users.show', $impersonated->id);
    }
}

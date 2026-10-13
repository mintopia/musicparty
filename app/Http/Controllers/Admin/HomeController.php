<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function dashboard(): View
    {
        $stats = (object) [
            'users' => (object) [
                'total' => User::count(),
                'lastWeek' => User::where('created_at', '>', Carbon::now()->subWeek())->count(),
            ],
        ];

        return view('admin.home.dashboard', [
            'stats' => $stats,
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

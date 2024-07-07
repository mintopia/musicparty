<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function dashboard()
    {
        $stats = (object)[
            'users' => (object)[
                'total' => User::count(),
                'lastWeek' => User::where('created_at', '>', Carbon::now()->subWeek())->count(),
            ]
        ];

        return view('admin.home.dashboard', [
            'stats' => $stats,
        ]);
    }

    public function unimpersonate(Request $request)
    {
        if (!$request->session()->get('impersonating')) {
            abort(403);
        }
        $impersonated = $request->user();
        $user = User::find($request->session()->get('originalUserId'));
        $request->session()->flush();
        $request->session()->regenerate(true);

        auth('web')->login($user);

        return response()->redirectToRoute('admin.users.show', $impersonated->id);
    }
}

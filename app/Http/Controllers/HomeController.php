<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function home(Request $request): RedirectResponse|Response
    {
        if (Setting::fetch('defaultparty')) {
            return response()->redirectToRoute('parties.show', Setting::fetch('defaultparty'));
        }

        $user = $this->resolveAuthenticatedUser($request);

        return Inertia::render('Home', [
            'user' => [
                'nickname' => $user->nickname,
                'email' => $user->getEmail(),
                'avatar' => $user->avatarUrl(),
            ],
        ]);
    }
}

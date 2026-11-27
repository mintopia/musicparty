<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\PartyListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function home(Request $request, PartyListing $listing): RedirectResponse|Response
    {
        if (Setting::fetch('defaultparty')) {
            return response()->redirectToRoute('parties.show', Setting::fetch('defaultparty'));
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return Inertia::render('Welcome', [
                'providers' => SocialProvider::whereAuthEnabled(true)->whereEnabled(true)->get()
                    ->map(fn ($provider) => ['code' => $provider->code, 'name' => $provider->name])
                    ->values(),
            ]);
        }

        if ($user->first_login) {
            return response()->redirectToRoute('login.signup');
        }

        return Inertia::render('Home', ['parties' => $listing->forMember($user)]);
    }
}

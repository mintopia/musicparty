<?php

namespace App\Http\Controllers;

use App\Domain\Admin\SiteSettings;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function home(Request $request, SiteSettings $site): Response|RedirectResponse
    {
        $defaultParty = $site->get('default_party');
        if ($defaultParty !== null && Party::query()->where('code', $defaultParty)->exists()) {
            return redirect('/parties/'.$defaultParty);
        }

        return Inertia::render('Home', [
            'canCreateParty' => $request->user()?->can('create', Party::class) ?? false,
        ]);
    }
}

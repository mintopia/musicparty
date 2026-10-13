<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function home(): RedirectResponse|View
    {
        if (Setting::fetch('defaultparty')) {
            return response()->redirectToRoute('parties.show', Setting::fetch('defaultparty'));
        }

        return view('home.home');
    }
}

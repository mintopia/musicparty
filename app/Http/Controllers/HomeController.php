<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class HomeController extends Controller
{
    public function home()
    {
        if (Setting::fetch('defaultparty')) {
            return response()->redirectToRoute('parties.show', Setting::fetch('defaultparty'));
        }
        return view('home.home');
    }
}

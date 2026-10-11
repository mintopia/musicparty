<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    public function start(int $user): RedirectResponse
    {
        abort(501);
    }

    public function stop(): RedirectResponse
    {
        abort(501);
    }
}

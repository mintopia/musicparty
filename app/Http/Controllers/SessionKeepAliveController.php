<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionKeepAliveController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->session()->put('keep_alive_at', now()->getTimestamp());

        return response()->noContent();
    }
}

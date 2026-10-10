<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectPlayerTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('sanctum')->user() instanceof User) {
            abort(403, 'Player Tokens cannot call this endpoint.');
        }

        return $next($request);
    }
}

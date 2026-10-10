<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayerToken
{
    public const ABILITY = 'player:connect';

    public function handle(Request $request, Closure $next): Response
    {
        $principal = Auth::guard('sanctum')->user();
        $party = $request->route('party');

        if (! $principal instanceof Party || ! $party instanceof Party || ! $principal->is($party) || ! $principal->tokenCan(self::ABILITY)) {
            abort(403, 'A Player Token for this Party is required.');
        }

        return $next($request);
    }
}

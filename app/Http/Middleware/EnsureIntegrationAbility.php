<?php

namespace App\Http\Middleware;

use App\Domain\Admin\Models\Integration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = Auth::guard('sanctum')->user();

        abort_if($token === null, 401);
        abort_unless($token instanceof Integration, 403, 'An Integration Token is required.');

        foreach ($abilities as $ability) {
            if (! $token->tokenCan($ability)) {
                abort(403);
            }
        }

        return $next($request);
    }
}

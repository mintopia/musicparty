<?php

namespace App\Http\Middleware;

use App\Models\IntegrationToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $request->user('integration');

        if (! $token instanceof IntegrationToken) {
            abort(401);
        }

        foreach ($abilities as $ability) {
            if (! $token->can($ability)) {
                abort(403);
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSignupComplete
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasCompletedSignup() || $request->routeIs('login*', 'logout', 'api.v1.me')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Finish signing up before continuing.',
                'code' => 'signup_required',
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('login.signup');
    }
}

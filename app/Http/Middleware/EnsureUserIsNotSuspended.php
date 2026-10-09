<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotSuspended
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUser = $request->user();
        $user = $sessionUser ?? ($request->bearerToken() !== null ? $request->user('sanctum') : null);

        if ($user === null || ! $user->suspended) {
            return $next($request);
        }

        if ($sessionUser !== null && $request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Your account has been suspended.'], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('login')->with('errorMessage', 'Your account has been suspended');
    }
}

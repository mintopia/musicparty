<?php

namespace App\Http\Middleware;

use App\Http\Concerns\ResolvesAuthenticatedUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectOnFirstLoginMiddleware
{
    use ResolvesAuthenticatedUser;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->resolveAuthenticatedUser($request)->first_login) {
            return response()->redirectToRoute('login.signup');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Show the branded party-not-found page, rather than a login redirect, for a guest following a link to an unknown party code.
     *
     * @param  array<int, string>  $guards
     */
    protected function unauthenticated($request, array $guards): never
    {
        $code = $request->route('party');

        if (is_string($code) && ! $request->expectsJson() && ! Party::query()->where('code', $code)->exists()) {
            throw (new ModelNotFoundException)->setModel(Party::class, [$code]);
        }

        parent::unauthenticated($request, $guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }
}

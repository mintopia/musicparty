<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [];

    protected function inExceptArray($request): bool
    {
        return parent::inExceptArray($request)
            || ($request->is('broadcasting/auth') && $request->bearerToken() !== null);
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

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
            || ($request->is('broadcasting/auth') && $this->hasValidSanctumToken($request));
    }

    private function hasValidSanctumToken(Request $request): bool
    {
        $plainTextToken = $request->bearerToken();

        if ($plainTextToken === null) {
            return false;
        }

        $token = PersonalAccessToken::findToken($plainTextToken);

        return $token !== null && ($token->expires_at === null || $token->expires_at->isFuture());
    }
}

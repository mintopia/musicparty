<?php

namespace App\Http\Middleware;

use App\Domain\Admin\IntegrationAbility;
use App\Models\IntegrationToken;
use App\Models\Party;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePartyExport
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user('integration');

        if ($token instanceof IntegrationToken) {
            abort_unless($token->can(IntegrationAbility::Export->value), 403);

            return $next($request);
        }

        $user = $request->user('sanctum');
        $party = $request->route('party');

        abort_unless($user instanceof User, 401);
        abort_unless($party instanceof Party && Gate::forUser($user)->allows('export', $party), 403);

        return $next($request);
    }
}

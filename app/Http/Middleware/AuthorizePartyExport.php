<?php

namespace App\Http\Middleware;

use App\Domain\Admin\IntegrationAbility;
use App\Domain\Admin\Models\Integration;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePartyExport
{
    public function handle(Request $request, Closure $next): Response
    {
        $principal = Auth::guard('sanctum')->user();

        if ($principal instanceof Integration) {
            abort_unless($principal->tokenCan(IntegrationAbility::Export->value), 403);

            return $next($request);
        }

        $user = $principal;
        $party = $request->route('party');

        abort_unless($user instanceof User, 401);
        abort_unless($party instanceof Party && Gate::forUser($user)->allows('export', $party), 403);

        return $next($request);
    }
}

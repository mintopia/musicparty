<?php

namespace App\Http\Middleware;

use App\Domain\Admin\IntegrationAbility;
use App\Domain\Admin\Models\Integration;
use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPrincipal
{
    /**
     * Admin Users pass the admin gate; Integration Tokens need the admin ability and act as the admin who issued them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $principal = Auth::guard('sanctum')->user();

        abort_if($principal === null, 401);

        if ($principal instanceof User) {
            abort_unless(Gate::forUser($principal)->allows('admin'), 403);

            return $next($request);
        }

        abort_unless($principal instanceof Integration && $principal->tokenCan(IntegrationAbility::Admin->value), 403);

        $issuer = $principal->issuer;

        abort_unless($issuer instanceof User && Gate::forUser($issuer)->allows('admin'), 403);

        $request->setUserResolver(fn (): User => $issuer);

        return $next($request);
    }
}

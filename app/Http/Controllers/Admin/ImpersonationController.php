<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\StartImpersonation;
use App\Domain\Admin\Actions\StopImpersonation;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user, StartImpersonation $startImpersonation): RedirectResponse
    {
        $startImpersonation->handle($request->user() ?? throw new AuthenticationException, $user);

        return redirect('/');
    }

    public function stop(Request $request, StopImpersonation $stopImpersonation): RedirectResponse
    {
        $impersonated = $request->user() ?? throw new AuthenticationException;

        if ($stopImpersonation->handle($impersonated) === null) {
            return redirect($request->user('web') === null ? route('login') : '/');
        }

        return redirect()->route('admin.users.show', $impersonated);
    }
}

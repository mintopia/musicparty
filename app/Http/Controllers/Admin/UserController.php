<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Actions\ListUsers;
use App\Domain\Admin\Actions\RevokeRole;
use App\Domain\Admin\Actions\SuspendUser;
use App\Domain\Admin\Actions\UnsuspendUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\V1\AdminUserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(ListUsersRequest $request, ListUsers $listUsers): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('Admin/Users/Index', [
            'users' => AdminUserResource::collection($listUsers->handle($search)),
            'filters' => ['search' => $search === '' ? null : $search],
        ]);
    }

    public function suspend(Request $request, User $user, SuspendUser $suspendUser): RedirectResponse
    {
        $suspendUser->handle($request->user() ?? throw new AuthenticationException, $user);

        return back();
    }

    public function unsuspend(Request $request, User $user, UnsuspendUser $unsuspendUser): RedirectResponse
    {
        $unsuspendUser->handle($request->user() ?? throw new AuthenticationException, $user);

        return back();
    }

    public function grantRole(RoleRequest $request, User $user, GrantRole $grantRole): RedirectResponse
    {
        $grantRole->handle($request->user() ?? throw new AuthenticationException, $user, $request->string('role')->toString());

        return back();
    }

    public function revokeRole(Request $request, User $user, string $role, RevokeRole $revokeRole): RedirectResponse
    {
        $revokeRole->handle($request->user() ?? throw new AuthenticationException, $user, $role);

        return back();
    }
}

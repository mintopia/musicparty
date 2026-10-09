<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Actions\ListUsers;
use App\Domain\Admin\Actions\RevokeRole;
use App\Domain\Admin\Actions\SuspendUser;
use App\Domain\Admin\Actions\UnsuspendUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListUsersRequest;
use App\Http\Requests\Api\V1\Admin\RoleRequest;
use App\Http\Resources\V1\AdminUserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(ListUsersRequest $request, ListUsers $listUsers): AnonymousResourceCollection
    {
        return AdminUserResource::collection($listUsers->handle($request->string('search')->toString()));
    }

    public function suspend(Request $request, User $user, SuspendUser $suspendUser): AdminUserResource
    {
        return new AdminUserResource($suspendUser->handle($request->user() ?? throw new AuthenticationException, $user)->load('roles'));
    }

    public function unsuspend(Request $request, User $user, UnsuspendUser $unsuspendUser): AdminUserResource
    {
        return new AdminUserResource($unsuspendUser->handle($request->user() ?? throw new AuthenticationException, $user)->load('roles'));
    }

    public function grantRole(RoleRequest $request, User $user, GrantRole $grantRole): AdminUserResource
    {
        return new AdminUserResource($grantRole->handle($request->user() ?? throw new AuthenticationException, $user, $request->string('role')->toString())->load('roles'));
    }

    public function revokeRole(Request $request, User $user, string $role, RevokeRole $revokeRole): AdminUserResource
    {
        return new AdminUserResource($revokeRole->handle($request->user() ?? throw new AuthenticationException, $user, $role)->load('roles'));
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Admin\Actions\DeleteUser;
use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Actions\ListUsers;
use App\Domain\Admin\Actions\RevokeRole;
use App\Domain\Admin\Actions\SuspendUser;
use App\Domain\Admin\Actions\UnsuspendUser;
use App\Domain\Admin\Actions\UpdateUser;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\DeleteUserRequest;
use App\Http\Requests\Api\V1\Admin\ListUsersRequest;
use App\Http\Requests\Api\V1\Admin\RoleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\V1\AdminUserDetailResource;
use App\Http\Resources\V1\AdminUserResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

    public function show(User $user): AdminUserDetailResource
    {
        return new AdminUserDetailResource($user->load(['roles', 'accounts.provider']));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): AdminUserDetailResource
    {
        $updateUser->handle(
            $request->user() ?? throw new AuthenticationException,
            $user,
            $request->has('nickname') ? $request->string('nickname')->toString() : (string) $user->nickname,
            $user->roles->pluck('code')->all(),
            $request->has('agreed_terms') ? $request->boolean('agreed_terms') : $user->terms_agreed_at !== null,
            $request->has('completed_signup') ? $request->boolean('completed_signup') : ! $user->first_login,
            (bool) $user->suspended,
        );

        return new AdminUserDetailResource($user->refresh()->load(['roles', 'accounts.provider']));
    }

    public function destroy(DeleteUserRequest $request, User $user, DeleteUser $deleteUser): Response
    {
        $deleteUser->handle($request->user() ?? throw new AuthenticationException, $user);

        return response()->noContent();
    }
}

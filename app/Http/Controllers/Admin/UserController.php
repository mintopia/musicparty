<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\DeleteUser;
use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Actions\ListUsers;
use App\Domain\Admin\Actions\RevokeRole;
use App\Domain\Admin\Actions\SuspendUser;
use App\Domain\Admin\Actions\UnsuspendUser;
use App\Domain\Admin\Actions\UpdateUser;
use App\Domain\Admin\AdminRole;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteUserRequest;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\V1\AdminUserDetail;
use App\Http\Resources\V1\AdminUserResource;
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
        $nickname = $request->string('nickname')->toString();

        return Inertia::render('Admin/Users/Index', [
            'users' => AdminUserResource::collection($listUsers->handle(
                $search,
                20,
                $request->filled('id') ? $request->integer('id') : null,
                $nickname,
                $request->string('order', 'id')->toString(),
                $request->string('order_direction', 'asc')->toString(),
            )),
            'filters' => [
                'search' => $search === '' ? null : $search,
                'id' => $request->filled('id') ? $request->integer('id') : null,
                'nickname' => $nickname === '' ? null : $nickname,
            ],
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

    public function show(User $user): Response
    {
        return Inertia::render('Admin/Users/Show', [
            'user' => (new AdminUserDetail)($user->load(['roles', 'accounts.provider'])),
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => (new AdminUserDetail)($user->load(['roles', 'accounts.provider'])),
            'roleOptions' => collect(AdminRole::cases())->map(fn (AdminRole $role): array => ['code' => $role->value, 'label' => $role->label()])->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle(
            $request->user() ?? throw new AuthenticationException,
            $user,
            $request->string('nickname')->toString(),
            $request->array('roles'),
            $request->boolean('agreed_terms'),
            $request->boolean('completed_signup'),
            $request->boolean('suspended'),
        );

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated.');
    }

    public function delete(User $user): Response
    {
        return Inertia::render('Admin/Users/Delete', [
            'user' => ['id' => $user->id, 'nickname' => $user->nickname],
            'parties' => $user->parties()->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($party): array => [
                'id' => $party->id,
                'name' => $party->name,
                'code' => $party->code,
            ])->all(),
        ]);
    }

    public function destroy(DeleteUserRequest $request, User $user, DeleteUser $deleteUser): RedirectResponse
    {
        $deleteUser->handle($request->user() ?? throw new AuthenticationException, $user);

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}

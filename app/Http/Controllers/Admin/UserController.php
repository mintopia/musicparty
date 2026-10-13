<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = (object) [];
        $query = User::query();

        if ($request->input('id')) {
            $filters->id = $request->input('id');
            $query = $query->whereId($filters->id);
        }

        if ($request->input('nickname')) {
            $filters->nickname = $request->input('nickname');
            $query = $query->where('nickname', 'LIKE', "%{$filters->nickname}%");
        }

        $params = (array) $filters;

        switch ($request->input('order')) {
            case 'nickname':
            case 'created_at':
                $params['order'] = $request->input('order');
                break;
            case 'id':
            default:
                $params['order'] = 'id';
                break;
        }

        switch ($request->input('order_direction', 'asc')) {
            case 'desc':
                $params['order_direction'] = 'desc';
                break;
            case 'asc':
            default:
                $params['order_direction'] = 'asc';
        }

        $query = $query->orderBy($params['order'], $params['order_direction']);

        $params['page'] = $request->input('page', 1);
        $params['perPage'] = $request->input('perPage', 20);

        $users = $query->paginate($params['perPage'])->appends($params);

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'params' => $params,
        ]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::all(),
            'currentRoles' => $user->roles()->pluck('code')->toArray(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->updateObject($user, $request);

        return response()->redirectToRoute('admin.users.show', $user->id)->with('successMessage', 'The user has been updated');
    }

    protected function updateObject(User $user, Request $request): void
    {
        $user->nickname = $request->input('nickname');

        if ($request->input('terms', false)) {
            $user->terms_agreed_at ??= now();
        } else {
            $user->terms_agreed_at = null;
        }

        $user->first_login = ! $request->input('first_login', false);
        $user->suspended = (bool) $request->input('suspended', false);

        $wantedRoles = $request->input('roles', []);
        $hasRoles = [];
        foreach ($user->roles as $role) {
            if (! in_array($role->code, $wantedRoles)) {
                $user->roles()->detach($role);

                continue;
            }
            $hasRoles[] = $role->code;
        }
        foreach ($wantedRoles as $role) {
            if (! in_array($role, $hasRoles)) {
                $role = Role::whereCode($role)->first();
                $user->roles()->attach($role);
            }
        }

        $user->save();
    }

    public function destroy(DeleteRequest $request, User $user): RedirectResponse
    {
        $user->delete();

        return response()->redirectToRoute('admin.users.index')->with('successMessage', 'The user has been deleted');
    }

    public function delete(User $user): View
    {
        return view('admin.users.delete', [
            'user' => $user,
        ]);
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $originalUser = $this->resolveAuthenticatedUser($request);
        $request->session()->flush();
        $request->session()->regenerate(true);
        auth('web')->login($user);
        $request->session()->put('originalUserId', $originalUser->id);
        $request->session()->put('impersonating', true);

        return response()->redirectToRoute('home');
    }
}

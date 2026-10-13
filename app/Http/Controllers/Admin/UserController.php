<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\LinkedAccount;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
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

        $users->through(fn (User $row): array => [
            'id' => $row->id,
            'nickname' => $row->nickname,
            'last_login' => $row->last_login?->format('Y-m-d H:i:s'),
            'last_login_human' => $row->last_login?->diffForHumans(),
            'created_at' => $row->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $row->created_at?->diffForHumans(),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'id' => $filters->id ?? null,
                'nickname' => $filters->nickname ?? null,
            ],
            'order' => $params['order'],
            'order_direction' => $params['order_direction'],
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'email' => $user->getEmail(),
                'suspended' => (bool) $user->suspended,
                'first_login' => (bool) $user->first_login,
                'terms_agreed' => (bool) $user->terms_agreed_at,
                'roles' => $user->roles->map(fn (Role $role): array => ['name' => $role->name])->values(),
                'last_login' => $user->last_login?->format('Y-m-d H:i:s'),
                'last_login_human' => $user->last_login?->diffForHumans(),
                'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
                'created_at_human' => $user->created_at?->diffForHumans(),
            ],
            'accounts' => $user->accounts->map(fn (LinkedAccount $account): array => [
                'id' => $account->id,
                'provider' => $account->provider?->name,
                'name' => $account->name,
                'email' => $account->email,
                'avatar_url' => $account->avatar_url,
                'external_id' => $account->external_id,
                'can_delete' => $account->canDelete(),
            ])->values(),
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'terms' => (bool) $user->terms_agreed_at,
                'first_login_completed' => ! $user->first_login,
                'suspended' => (bool) $user->suspended,
            ],
            'roles' => Role::all()->map(fn (Role $role): array => ['code' => $role->code, 'name' => $role->name])->values(),
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

    public function delete(User $user): Response
    {
        return Inertia::render('Admin/Users/Delete', [
            'user' => ['id' => $user->id, 'nickname' => $user->nickname],
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

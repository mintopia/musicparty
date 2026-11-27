<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\LinkedAccount;
use App\Models\PartyMember;
use App\Models\Role;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const MANAGED_ROLES = ['admin'];

    public function index(Request $request): Response
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:admin'],
            'suspended' => ['nullable', 'boolean'],
            'first_login' => ['nullable', 'boolean'],
            'provider' => ['nullable', 'string', 'max:50'],
            'spotify_relink' => ['nullable', 'boolean'],
        ]);
        $filters = (object) [];
        $query = User::query();
        $total = User::query()->count();

        $search = $this->searchTerms($request);
        if ($search['q'] !== '') {
            $filters->q = $search['q'];
        }
        if ($search['id'] !== null) {
            $query->whereKey($search['id']);
        }
        if ($search['nickname'] !== null) {
            $query->where('nickname', 'LIKE', '%'.addcslashes($search['nickname'], '%_\\').'%');
        }
        if ($search['text'] !== '') {
            $like = '%'.addcslashes($search['text'], '%_\\').'%';
            $query->where(fn ($inner) => $inner
                ->where('nickname', 'LIKE', $like)
                ->when(ctype_digit($search['text']), fn ($digits) => $digits->orWhere('id', (int) $search['text'])));
        }

        if ($request->input('role') === 'admin') {
            $filters->role = 'admin';
            $query->whereHas('roles', fn ($roles) => $roles->where('code', 'admin'));
        }
        if ($request->boolean('suspended')) {
            $filters->suspended = 1;
            $query->where('suspended', true);
        }
        if ($request->boolean('first_login')) {
            $filters->first_login = 1;
            $query->where('first_login', true);
        }
        $provider = (string) $request->input('provider', '');
        if ($provider !== '') {
            $filters->provider = $provider;
            $query->whereHas('accounts.provider', fn ($providers) => $providers->where('code', $provider));
        }

        if ($request->boolean('spotify_relink')) {
            $filters->spotify_relink = 1;
            $query->whereHas('accounts', fn ($accounts) => $accounts
                ->whereNotNull('needs_relink_at')
                ->whereHas('provider', fn ($providers) => $providers->where('code', 'spotify')));
        }

        $params = (array) $filters;

        switch ($request->input('order')) {
            case 'id':
            case 'nickname':
            case 'created_at':
                $params['order'] = $request->input('order');
                break;
            case 'last_login':
            default:
                $params['order'] = 'last_login';
                break;
        }

        $defaultDirection = $params['order'] === 'last_login' || $params['order'] === 'created_at' ? 'desc' : 'asc';
        $params['order_direction'] = $request->input('order_direction', $defaultDirection) === 'asc' ? 'asc' : 'desc';

        if ($params['order'] === 'last_login') {
            $query->orderByRaw('last_login IS NULL')->orderBy('last_login', $params['order_direction']);
        } else {
            $query->orderBy($params['order'], $params['order_direction']);
        }
        $query->orderBy('id');

        $params['page'] = $request->input('page', 1);
        $params['perPage'] = $request->input('perPage', 20);

        $users = $query->withExists(['roles as is_admin' => fn ($roles) => $roles->where('code', 'admin')])
            ->withCount('partyMembers')
            ->with('accounts.provider')
            ->paginate($params['perPage'])->appends($params);

        $users->through(fn (User $row): array => [
            'id' => $row->id,
            'nickname' => $row->nickname,
            'is_admin' => (bool) $row->getAttribute('is_admin'),
            'suspended' => (bool) $row->suspended,
            'avatar_url' => $row->avatarUrl(),
            'accounts' => $row->accounts->map(fn (LinkedAccount $account): array => [
                'provider' => $account->provider?->code,
                'name' => $account->provider?->name,
                'needs_relink' => $account->needs_relink_at !== null,
            ])->values()->all(),
            'parties_count' => (int) $row->getAttribute('party_members_count'),
            'last_login' => $row->last_login?->format('Y-m-d H:i:s'),
            'last_login_human' => $row->last_login?->diffForHumans(),
            'created_at' => $row->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $row->created_at?->diffForHumans(),
        ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'q' => $filters->q ?? '',
                'role' => $filters->role ?? '',
                'suspended' => isset($filters->suspended),
                'first_login' => isset($filters->first_login),
                'provider' => $filters->provider ?? '',
                'spotify_relink' => isset($filters->spotify_relink),
            ],
            'providers' => SocialProvider::query()->where('supports_auth', true)->orderBy('name')->get(['code', 'name'])
                ->map(fn (SocialProvider $row): array => ['code' => $row->code, 'name' => $row->name])->all(),
            'total' => $total,
            'order' => $params['order'],
            'order_direction' => $params['order_direction'],
        ]);
    }

    /**
     * Splits the search box into `id:`/`nickname:` terms and free text; the legacy `id` and `nickname` query parameters are folded in.
     *
     * @return array{q: string, id: int|null, nickname: string|null, text: string}
     */
    protected function searchTerms(Request $request): array
    {
        $raw = trim((string) $request->input('q', ''));
        if ($raw === '') {
            $legacy = array_filter([
                $request->input('id') ? 'id:'.$request->input('id') : null,
                $request->input('nickname') ? 'nickname:'.$request->input('nickname') : null,
            ]);
            $raw = implode(' ', $legacy);
        }

        $id = null;
        $nickname = null;
        $text = preg_replace_callback('/\b(id|nickname):(\S+)/i', function (array $match) use (&$id, &$nickname): string {
            if (strtolower($match[1]) === 'id') {
                $id = (int) $match[2];
            } else {
                $nickname = $match[2];
            }

            return '';
        }, $raw) ?? $raw;

        return ['q' => $raw, 'id' => $id, 'nickname' => $nickname, 'text' => trim($text)];
    }

    public function show(User $user): Response
    {
        $user->load(['accounts.provider', 'roles', 'partyMembers.party', 'partyMembers.role']);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'email' => $user->getEmail(),
                'avatar_url' => $user->avatarUrl(),
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
                'provider_code' => $account->provider?->code,
                'linked_human' => $account->created_at?->diffForHumans(),
                'linked_at' => $account->created_at?->format('Y-m-d H:i:s'),
                'needs_relink' => $account->needs_relink_at !== null,
                'token_expires_human' => $account->access_token_expires_at
                    ? Carbon::parse($account->access_token_expires_at)->diffForHumans()
                    : null,
                'token_expired' => $account->access_token_expires_at
                    ? Carbon::parse($account->access_token_expires_at)->isPast()
                    : false,
                'can_delete' => $account->canDelete(),
            ])->values(),
            'parties' => $user->partyMembers
                ->map(fn (PartyMember $member): array => [
                    'code' => $member->party->code,
                    'name' => $member->party->name,
                    'role' => $member->role->name,
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
            'roles' => Role::query()->where('code', 'admin')->get()->map(fn (Role $role): array => ['code' => $role->code, 'name' => $role->name])->values(),
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

        $wantedRoles = array_values(array_intersect($request->input('roles', []), self::MANAGED_ROLES));
        $hasRoles = [];
        foreach ($user->roles as $role) {
            if (! in_array($role->code, self::MANAGED_ROLES)) {
                continue;
            }
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
        $user->load(['accounts.provider']);

        return Inertia::render('Admin/Users/Delete', [
            'user' => ['id' => $user->id, 'nickname' => $user->nickname],
            'impact' => [
                'accounts' => $user->accounts->map(fn (LinkedAccount $account): ?string => $account->provider?->name)->filter()->values()->all(),
                'accounts_count' => $user->accounts->count(),
                'memberships_count' => $user->partyMembers()->count(),
                'owned_parties' => $user->parties()->orderBy('name')->pluck('name')->all(),
            ],
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

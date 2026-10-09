<?php

use App\Domain\Admin\Actions\EnterActAsHost;
use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Actions\LeaveActAsHost;
use App\Domain\Admin\Actions\RevokeRole;
use App\Domain\Admin\Actions\SuspendUser;
use App\Models\AdminAuditEntry;
use App\Models\AdminHostSession;
use App\Models\Party;
use App\Models\PartyLog;
use App\Models\Role;
use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Telescope;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function makeAdmin(): User
{
    $user = User::factory()->create();
    app(GrantRole::class)->handle($user, $user, 'admin');
    AdminAuditEntry::query()->delete();

    return $user->fresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeParty(array $attributes = []): Party
{
    return Party::withoutEvents(fn (): Party => Party::factory()->create($attributes));
}

function resolveAdminUri(string $uri): string
{
    return strtr($uri, ['{user}' => (string) User::factory()->create()->id, '{party}' => (string) makeParty()->id]);
}

function makeUserWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::factory()->create(['code' => $role, 'name' => $role]));

    return $user;
}

$webRoutes = [
    'dashboard' => ['get', '/admin'],
    'users' => ['get', '/admin/users'],
    'parties' => ['get', '/admin/parties'],
    'suspend' => ['post', '/admin/users/{user}/suspend'],
    'unsuspend' => ['delete', '/admin/users/{user}/suspend'],
    'grant' => ['post', '/admin/users/{user}/roles'],
    'revoke' => ['delete', '/admin/users/{user}/roles/admin'],
    'enter' => ['post', '/admin/parties/{party}/act-as-host'],
    'leave' => ['delete', '/admin/parties/{party}/act-as-host'],
    'theme' => ['get', '/admin/theme'],
    'theme save' => ['put', '/admin/theme'],
    'theme reset' => ['delete', '/admin/theme'],
];

$apiRoutes = [
    'users' => ['getJson', '/api/v1/admin/users'],
    'suspend' => ['postJson', '/api/v1/admin/users/{user}/suspension'],
    'unsuspend' => ['deleteJson', '/api/v1/admin/users/{user}/suspension'],
    'grant' => ['postJson', '/api/v1/admin/users/{user}/roles'],
    'revoke' => ['deleteJson', '/api/v1/admin/users/{user}/roles/admin'],
    'enter' => ['postJson', '/api/v1/admin/parties/{party}/act-as-host'],
    'leave' => ['deleteJson', '/api/v1/admin/parties/{party}/act-as-host'],
    'theme' => ['getJson', '/api/v1/admin/theme'],
    'theme save' => ['putJson', '/api/v1/admin/theme'],
    'theme reset' => ['deleteJson', '/api/v1/admin/theme'],
];

it('refuses non-admins including create-party users on every admin web route', function (string $method, string $uri): void {
    $this->actingAs(makeUserWithRole('create-party'))->{$method}(resolveAdminUri($uri))->assertForbidden();
})->with($webRoutes);

it('redirects guests on every admin web route to login', function (string $method, string $uri): void {
    $this->{$method}(resolveAdminUri($uri))->assertRedirect(route('login'));
})->with($webRoutes);

it('refuses non-admins on every admin api route', function (string $method, string $uri): void {
    Sanctum::actingAs(makeUserWithRole('create-party'));

    $this->{$method}(resolveAdminUri($uri))->assertForbidden();
})->with($apiRoutes);

it('rejects guests on every admin api route', function (string $method, string $uri): void {
    $this->{$method}(resolveAdminUri($uri))->assertUnauthorized();
})->with($apiRoutes);

it('keeps horizon and pulse behind the admin gate', function (string $uri): void {
    $this->get($uri)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($uri)->assertForbidden();
    $this->actingAs(makeAdmin())->get($uri)->assertSuccessful();
})->with(['/horizon', '/pulse']);

it('allows only admins through the horizon, pulse and telescope gates', function (string $ability): void {
    expect(Gate::forUser(makeAdmin())->allows($ability))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows($ability))->toBeFalse()
        ->and(Gate::forUser(makeUserWithRole('create-party'))->allows($ability))->toBeFalse();
})->with(['viewHorizon', 'viewPulse', 'viewTelescope']);

it('refuses non-admins at the telescope authorisation middleware', function (): void {
    config(['telescope.enabled' => true]);
    $provider = app()->getProvider(TelescopeServiceProvider::class);
    (fn () => $this->authorization())->call($provider);
    $middleware = new Authorize;
    $next = fn () => response('ok');
    $as = function (User $user) use ($middleware, $next) {
        $this->actingAs($user);

        return $middleware->handle(Request::create('/telescope'), $next);
    };

    expect(fn () => $as(User::factory()->create()))->toThrow(HttpException::class)
        ->and($as(makeAdmin())->getContent())->toBe('ok');
});

afterEach(fn () => Telescope::auth(fn (): bool => app()->environment('local')));

it('turns telescope off by default', function (): void {
    expect(file_get_contents(config_path('telescope.php')))->toContain("env('TELESCOPE_ENABLED', false)");
});

it('shows the dashboard links to admins', function (): void {
    $this->withoutVite()->actingAs(makeAdmin())->get(route('admin.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Admin/Dashboard')
            ->where('links.horizon', url('/horizon'))
            ->where('links.pulse', url('/pulse'))
            ->where('links.telescope', null));

    config(['telescope.enabled' => true]);

    $this->withoutVite()->actingAs(makeAdmin())->get(route('admin.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('links.telescope', url('/telescope')));
});

it('shares is_admin true for admins', function (): void {
    $this->withoutVite()->actingAs(makeAdmin())->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('auth.user.is_admin', true));
});

it('shares is_admin false for non-admins', function (): void {
    $this->withoutVite()->actingAs(User::factory()->create()->fresh())->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('auth.user.is_admin', false));
});

it('shares no user for guests', function (): void {
    $this->withoutVite()->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('auth.user', null));
});

it('lists and searches users with pagination', function (): void {
    $admin = makeAdmin();
    User::factory()->create(['nickname' => 'zelda_fan']);
    User::factory()->create(['nickname' => 'mario']);
    User::factory()->count(30)->create();

    $this->withoutVite()->actingAs($admin)->get(route('admin.users.index', ['search' => 'zelda']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.nickname', 'zelda_fan')
            ->where('users.data.0.suspended', false)
            ->where('users.data.0.roles', [])
            ->where('filters.search', 'zelda'));

    $this->withoutVite()->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->has('users.data', 25)->has('users.links')->where('filters.search', null));

    $this->actingAs($admin)->get(route('admin.users.index', ['search' => '%']))
        ->assertInertia(fn (Assert $page): Assert => $page->has('users.data', 0));
});

it('lists users through the api', function (): void {
    Sanctum::actingAs(makeAdmin());
    User::factory()->create(['nickname' => 'zelda_fan']);

    $this->getJson('/api/v1/admin/users?search=zelda')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nickname', 'zelda_fan')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('suspends and unsuspends over the web, auditing both', function (): void {
    $admin = makeAdmin();
    $user = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.suspend', $user))->assertRedirect();
    expect($user->fresh()->suspended)->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.users.unsuspend', $user))->assertRedirect();
    expect($user->fresh()->suspended)->toBeFalse()
        ->and(AdminAuditEntry::query()->orderBy('id')->pluck('action')->all())->toBe(['user.suspended', 'user.unsuspended'])
        ->and(AdminAuditEntry::query()->first())->admin_id->toBe($admin->id)->subject_user_id->toBe($user->id)->created_at->not->toBeNull();
});

it('suspends and unsuspends over the api', function (): void {
    Sanctum::actingAs(makeAdmin());
    $user = User::factory()->create();

    $this->postJson("/api/v1/admin/users/{$user->id}/suspension")->assertOk()->assertJsonPath('data.suspended', true);
    $this->deleteJson("/api/v1/admin/users/{$user->id}/suspension")->assertOk()->assertJsonPath('data.suspended', false);
});

it('does not audit a repeated suspension', function (): void {
    $admin = makeAdmin();
    $user = User::factory()->create();

    app(SuspendUser::class)->handle($admin, $user);
    app(SuspendUser::class)->handle($admin, $user);

    expect(AdminAuditEntry::query()->count())->toBe(1);
});

it('refuses self suspension on web and api', function (): void {
    $admin = makeAdmin();

    $this->actingAs($admin)->from('/admin/users')->post(route('admin.users.suspend', $admin))
        ->assertRedirect('/admin/users')->assertSessionHasErrors('admin');

    Sanctum::actingAs($admin);
    $this->postJson("/api/v1/admin/users/{$admin->id}/suspension")->assertUnprocessable()->assertJsonValidationErrors('admin');

    expect($admin->fresh()->suspended)->toBeFalse()->and(AdminAuditEntry::query()->count())->toBe(0);
});

it('revokes tokens when suspending', function (): void {
    $user = User::factory()->create();
    $user->createToken('t');

    app(SuspendUser::class)->handle(makeAdmin(), $user);
    expect($user->tokens()->count())->toBe(0);
});

it('rejects a suspended user bearing a still-valid token', function (): void {
    $other = User::factory()->create();
    $kept = $other->createToken('t')->plainTextToken;
    $other->forceFill(['suspended' => true])->save();

    $this->withToken($kept)->putJson('/api/v1/me/colour-scheme', ['colour_scheme' => 'dark'])
        ->assertForbidden()->assertJsonPath('message', 'Your account has been suspended.');
});

it('logs a suspended session out and redirects to login', function (): void {
    $user = User::factory()->create(['suspended' => true]);

    $this->actingAs($user)->get(route('home'))
        ->assertRedirect(route('login'))->assertSessionHas('errorMessage', 'Your account has been suspended');

    $this->assertGuest();
});

it('rejects a suspended session on the api', function (): void {
    $this->actingAs(User::factory()->create(['suspended' => true]))
        ->putJson('/api/v1/me/colour-scheme', ['colour_scheme' => 'dark'])->assertForbidden();
});

it('lets unsuspended users continue', function (): void {
    $this->actingAs(User::factory()->create())->put(route('colour-scheme.update'), ['colour_scheme' => 'dark'])->assertRedirect();
});

it('grants and revokes roles creating missing role rows', function (): void {
    $admin = makeAdmin();
    $user = User::factory()->create();
    expect(Role::query()->where('code', 'create-party')->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.users.roles.grant', $user), ['role' => 'create-party'])->assertRedirect();
    expect($user->fresh()->hasRole('create-party'))->toBeTrue();

    $this->actingAs($admin)->post(route('admin.users.roles.grant', $user), ['role' => 'create-party'])->assertRedirect();
    expect($user->roles()->count())->toBe(1);

    $this->actingAs($admin)->delete(route('admin.users.roles.revoke', [$user, 'create-party']))->assertRedirect();
    expect($user->fresh()->hasRole('create-party'))->toBeFalse()
        ->and(AdminAuditEntry::query()->orderBy('id')->get()->map(fn ($e) => [$e->action, $e->meta])->all())
        ->toBe([['role.granted', ['role' => 'create-party']], ['role.revoked', ['role' => 'create-party']]]);
});

it('manages roles through the api', function (): void {
    Sanctum::actingAs(makeAdmin());
    $user = User::factory()->create();

    $this->postJson("/api/v1/admin/users/{$user->id}/roles", ['role' => 'admin'])->assertOk()->assertJsonPath('data.roles', ['admin']);
    $this->deleteJson("/api/v1/admin/users/{$user->id}/roles/admin")->assertOk()->assertJsonPath('data.roles', []);
});

it('rejects unknown or missing roles', function (array $payload): void {
    Sanctum::actingAs(makeAdmin());
    $user = User::factory()->create();

    $this->postJson("/api/v1/admin/users/{$user->id}/roles", $payload)->assertUnprocessable()->assertJsonValidationErrors('role');
    $this->deleteJson("/api/v1/admin/users/{$user->id}/roles/host")->assertUnprocessable()->assertJsonValidationErrors('admin');
    expect(Role::query()->where('code', 'host')->exists())->toBeFalse();
})->with(['unknown' => [['role' => 'host']], 'missing' => [[]]]);

it('protects the last admin', function (): void {
    $admin = makeAdmin();

    expect(fn () => app(RevokeRole::class)->handle($admin, $admin, 'admin'))->toThrow(ValidationException::class);
    expect($admin->fresh()->hasRole('admin'))->toBeTrue();

    $this->actingAs($admin)->from('/admin/users')->delete(route('admin.users.roles.revoke', [$admin, 'admin']))->assertSessionHasErrors('admin');

    $second = User::factory()->create();
    app(GrantRole::class)->handle($admin, $second, 'admin');
    app(RevokeRole::class)->handle($admin, $admin, 'admin');

    expect($admin->fresh()->hasRole('admin'))->toBeFalse();
});

it('revoking a role the user lacks is a silent no-op', function (): void {
    app(RevokeRole::class)->handle(makeAdmin(), User::factory()->create(), 'create-party');

    expect(AdminAuditEntry::query()->count())->toBe(0);
});

it('lists parties with the act-as-host flag for the current admin', function (): void {
    $admin = makeAdmin();
    $entered = makeParty(['name' => 'Alpha']);
    makeParty(['name' => 'Beta']);
    app(EnterActAsHost::class)->handle($admin, $entered);
    app(EnterActAsHost::class)->handle(makeAdmin(), Party::query()->where('name', 'Beta')->first());

    $this->withoutVite()->actingAs($admin)->get(route('admin.parties.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Admin/Parties/Index')
            ->where('parties.0.name', 'Alpha')->where('parties.0.acting_as_host', true)
            ->where('parties.1.name', 'Beta')->where('parties.1.acting_as_host', false));
});

it('enters and leaves act-as-host over the web writing the party log', function (): void {
    $admin = makeAdmin();
    $party = makeParty();

    expect($admin->isActingAsHostIn($party))->toBeFalse();

    $this->actingAs($admin)->post(route('admin.parties.act-as-host.enter', $party))->assertRedirect();
    expect($admin->isActingAsHostIn($party))->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.parties.act-as-host.leave', $party))->assertRedirect();
    expect($admin->isActingAsHostIn($party))->toBeFalse()
        ->and(PartyLog::query()->orderBy('id')->get()->map(fn ($e) => [$e->action, $e->acting_as_host, $e->user_id, $e->party_id])->all())
        ->toBe([['act_as_host.entered', true, $admin->id, $party->id], ['act_as_host.left', true, $admin->id, $party->id]]);
});

it('enters and leaves act-as-host over the api', function (): void {
    $admin = makeAdmin();
    $party = makeParty();
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/parties/{$party->id}/act-as-host")->assertOk()->assertJsonPath('data.acting_as_host', true);
    $this->deleteJson("/api/v1/admin/parties/{$party->id}/act-as-host")->assertOk()->assertJsonPath('data.acting_as_host', false);

    expect(PartyLog::query()->count())->toBe(2);
});

it('does not duplicate or log no-op act-as-host transitions', function (): void {
    $admin = makeAdmin();
    $party = makeParty();

    app(LeaveActAsHost::class)->handle($admin, $party);
    app(EnterActAsHost::class)->handle($admin, $party);
    app(EnterActAsHost::class)->handle($admin, $party);

    expect(PartyLog::query()->count())->toBe(1)->and(AdminHostSession::query()->count())->toBe(1);
});

it('gives no host powers to a non-admin or a demoted admin', function (): void {
    $party = makeParty();

    expect(fn () => app(EnterActAsHost::class)->handle(User::factory()->create(), $party))->toThrow(AuthorizationException::class);

    $admin = makeAdmin();
    app(EnterActAsHost::class)->handle($admin, $party);
    $admin->roles()->detach();

    expect($admin->fresh()->isActingAsHostIn($party))->toBeFalse();
});

it('grants management of a party only while acting as host', function (): void {
    $admin = makeAdmin();
    $party = makeParty();

    expect($party->canBeManagedBy($admin))->toBeFalse();

    app(EnterActAsHost::class)->handle($admin, $party);
    expect($party->canBeManagedBy($admin))->toBeTrue();

    app(LeaveActAsHost::class)->handle($admin, $party);
    expect($party->canBeManagedBy($admin))->toBeFalse();
});

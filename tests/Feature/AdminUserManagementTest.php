<?php

use App\Domain\Admin\Actions\DeleteUser;
use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Support\Realtime\Contracts\RealtimeConnections;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function adminUser(): User
{
    $user = User::factory()->withRole('admin')->create();
    AdminAuditEntry::query()->delete();

    return $user->fresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function partyFor(array $attributes): Party
{
    return Party::withoutEvents(fn (): Party => Party::factory()->create($attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function linkAccount(?User $user = null, array $attributes = []): LinkedAccount
{
    return LinkedAccount::factory()->create([
        'user_id' => $user instanceof User ? $user->id : User::factory()->create()->id,
        'social_provider_id' => SocialProvider::factory()->create(['code' => 'p'.uniqid()])->id,
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    $this->mock(RealtimeConnections::class)->shouldIgnoreMissing();
    $this->admin = adminUser();
});

it('filters users by id and nickname', function (): void {
    User::factory()->create(['nickname' => 'alpha']);
    $beta = User::factory()->create(['nickname' => 'beta']);

    $this->withoutVite()->actingAs($this->admin)->get(route('admin.users.index', ['id' => $beta->id]))
        ->assertInertia(fn (Assert $page): Assert => $page->has('users.data', 1)->where('users.data.0.id', $beta->id)->where('filters.id', $beta->id));

    $this->withoutVite()->actingAs($this->admin)->get(route('admin.users.index', ['nickname' => 'alph']))
        ->assertInertia(fn (Assert $page): Assert => $page->has('users.data', 1)->where('users.data.0.nickname', 'alpha')->where('filters.nickname', 'alph'));
});

it('sorts users by whitelisted columns and defaults to id ascending', function (string $order, string $direction, string $expected): void {
    $old = User::factory()->create(['nickname' => 'aaa', 'last_login' => now()->subDay(), 'created_at' => now()->subYear()]);
    $new = User::factory()->create(['nickname' => 'zzz', 'last_login' => now(), 'created_at' => now()->subDay()]);
    User::query()->whereKey($this->admin->id)->update(['nickname' => 'mmm', 'last_login' => now()->subWeek(), 'created_at' => now()->subYears(2)]);
    $ids = ['old' => $old->id, 'new' => $new->id];

    $this->withoutVite()->actingAs($this->admin)->get(route('admin.users.index', ['order' => $order, 'order_direction' => $direction]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('users.data.0.id', $ids[$expected] ?? $this->admin->id));
})->with([
    'nickname desc' => ['nickname', 'desc', 'new'],
    'nickname asc' => ['nickname', 'asc', 'old'],
    'last_login desc' => ['last_login', 'desc', 'new'],
    'created_at asc' => ['created_at', 'asc', 'admin'],
    'id asc' => ['id', 'asc', 'admin'],
    'id desc' => ['id', 'desc', 'new'],
]);

it('rejects an unknown sort column or direction', function (array $query, string $field): void {
    $this->actingAs($this->admin)->get(route('admin.users.index', $query))->assertSessionHasErrors($field);
})->with([
    'column' => [['order' => 'password'], 'order'],
    'direction' => [['order_direction' => 'sideways'], 'order_direction'],
    'id' => [['id' => 'abc'], 'id'],
]);

it('exposes last login and created at on the list', function (): void {
    $this->withoutVite()->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->has('users.data.0.created_at')->has('users.data.0.last_login'));
});

it('shows a user with linked accounts and flags', function (): void {
    $user = User::factory()->create(['nickname' => 'target']);
    $provider = SocialProvider::factory()->create(['name' => 'Discord', 'code' => 'discord-test']);
    linkAccount($user, ['social_provider_id' => $provider->id, 'email' => 'a@example.com', 'external_id' => '99']);

    $this->withoutVite()->actingAs($this->admin)->get(route('admin.users.show', $user))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Admin/Users/Show')
            ->where('user.nickname', 'target')
            ->where('user.email', 'a@example.com')
            ->where('user.completed_signup', true)
            ->where('user.agreed_terms', true)
            ->where('user.suspended', false)
            ->has('user.accounts', 1)
            ->where('user.accounts.0.provider', 'Discord')
            ->where('user.accounts.0.external_id', '99')
            ->where('user.accounts.0.can_delete', false));
});

it('returns 404 for a missing user', function (): void {
    $this->actingAs($this->admin)->get('/admin/users/999999')->assertNotFound();
});

it('renders the edit page with role options', function (): void {
    $user = User::factory()->create();

    $this->withoutVite()->actingAs($this->admin)->get(route('admin.users.edit', $user))
        ->assertInertia(fn (Assert $page): Assert => $page->component('Admin/Users/Edit')->has('roleOptions', 2)->where('user.id', $user->id));
});

it('updates a user, roles, flags and suspension with audit', function (): void {
    $user = User::factory()->firstLogin()->create(['nickname' => 'old']);

    $this->actingAs($this->admin)->patch(route('admin.users.update', $user), [
        'nickname' => 'renamed',
        'roles' => ['create-party'],
        'agreed_terms' => true,
        'completed_signup' => true,
        'suspended' => true,
    ])->assertRedirect(route('admin.users.show', $user))->assertSessionHas('success');

    $user->refresh();
    expect($user->nickname)->toBe('renamed')
        ->and($user->terms_agreed_at)->not->toBeNull()
        ->and($user->first_login)->toBeFalse()
        ->and($user->suspended)->toBeTrue()
        ->and($user->hasRole('create-party'))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse();
    expect(AdminAuditEntry::query()->pluck('action')->all())->toEqualCanonicalizing(['user.updated', 'role.granted', 'user.suspended']);
});

it('clears flags, removes roles and unsuspends', function (): void {
    $user = User::factory()->suspended()->withRole('create-party')->create();

    $this->actingAs($this->admin)->patch(route('admin.users.update', $user), [
        'nickname' => $user->nickname,
        'roles' => [],
        'agreed_terms' => false,
        'completed_signup' => false,
        'suspended' => false,
    ])->assertRedirect();

    $user->refresh();
    expect($user->terms_agreed_at)->toBeNull()
        ->and($user->first_login)->toBeTrue()
        ->and($user->suspended)->toBeFalse()
        ->and($user->hasRole('create-party'))->toBeFalse();
});

it('validates user updates', function (array $payload, string $field): void {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.users.update', $user), $payload + [
        'nickname' => 'ok', 'roles' => [], 'agreed_terms' => true, 'completed_signup' => true, 'suspended' => false,
    ])->assertSessionHasErrors($field);
})->with([
    'blank nickname' => [['nickname' => ''], 'nickname'],
    'long nickname' => [['nickname' => str_repeat('a', 256)], 'nickname'],
    'unknown role' => [['roles' => ['host']], 'roles.0'],
    'bad flag' => [['suspended' => 'maybe'], 'suspended'],
]);

it('refuses to suspend yourself or remove the last admin through edit', function (): void {
    $this->actingAs($this->admin)->from('/x')->patch(route('admin.users.update', $this->admin), [
        'nickname' => 'me', 'roles' => ['admin'], 'agreed_terms' => true, 'completed_signup' => true, 'suspended' => true,
    ])->assertSessionHasErrors('admin');

    $this->actingAs($this->admin)->from('/x')->patch(route('admin.users.update', $this->admin), [
        'nickname' => 'me', 'roles' => [], 'agreed_terms' => true, 'completed_signup' => true, 'suspended' => false,
    ])->assertSessionHasErrors('admin');

    expect($this->admin->fresh()->hasRole('admin'))->toBeTrue()->and($this->admin->fresh()->nickname)->not->toBe('me');
});

it('lists owned parties on the delete page', function (): void {
    $user = User::factory()->create();
    partyFor(['user_id' => $user->id, 'name' => 'Doomed']);

    $this->withoutVite()->actingAs($this->admin)->get(route('admin.users.delete', $user))
        ->assertInertia(fn (Assert $page): Assert => $page->component('Admin/Users/Delete')->has('parties', 1)->where('parties.0.name', 'Doomed'));
});

it('deletes a user after typed confirmation, cascading and auditing', function (): void {
    $user = User::factory()->create();
    $party = partyFor(['user_id' => $user->id]);
    linkAccount($user);

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user), ['confirm' => 'delete'])
        ->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

    expect(User::query()->find($user->id))->toBeNull()
        ->and($party->fresh())->toBeNull()
        ->and(LinkedAccount::query()->where('user_id', $user->id)->exists())->toBeFalse();
    $entry = AdminAuditEntry::query()->where('action', 'user.deleted')->firstOrFail();
    expect($entry->subject_user_id)->toBeNull()
        ->and($entry->subject_name)->toBe($user->nickname)
        ->and($entry->admin_id)->toBe($this->admin->id)
        ->and($entry->admin_name)->toBe($this->admin->nickname)
        ->and($entry->meta['nickname'] ?? null)->toBe($user->nickname)
        ->and($entry->meta['user_id'] ?? null)->toBe($user->id);
});

it('keeps audit entries authored by a deleted admin and records who deleted them', function (): void {
    $other = User::factory()->withRole('admin')->create();
    $victim = User::factory()->create();
    app(RecordAdminAudit::class)->handle($other, 'user.suspended', $victim);
    $authoredId = AdminAuditEntry::query()->where('admin_id', $other->id)->value('id');

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $other), ['confirm' => 'delete'])
        ->assertRedirect(route('admin.users.index'));

    $authored = AdminAuditEntry::query()->findOrFail($authoredId);
    assert($authored instanceof AdminAuditEntry);
    $deleted = AdminAuditEntry::query()->where('action', 'user.deleted')->firstOrFail();
    $deletedMeta = (array) $deleted->meta;
    expect($authored->admin_id)->toBeNull()
        ->and($authored->admin_name)->toBe($other->nickname)
        ->and($deleted->admin_name)->toBe($this->admin->nickname)
        ->and($deletedMeta['user_id'])->toBe($other->id);

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $victim), ['confirm' => 'delete']);
    $reloaded = AdminAuditEntry::query()->findOrFail($authoredId);
    assert($reloaded instanceof AdminAuditEntry);
    expect($reloaded->subject_user_id)->toBeNull()
        ->and($reloaded->subject_name)->toBe($victim->nickname);
});

it('requires the exact confirmation word to delete', function (?string $confirm): void {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user), $confirm === null ? [] : ['confirm' => $confirm])
        ->assertSessionHasErrors('confirm');
    expect($user->fresh())->not->toBeNull();
})->with([null, '', 'Delete', 'remove']);

it('refuses to delete yourself', function (): void {
    $this->actingAs($this->admin)->from('/x')->delete(route('admin.users.destroy', $this->admin), ['confirm' => 'delete'])
        ->assertSessionHasErrors('admin');
    expect($this->admin->fresh())->not->toBeNull();
});

it('refuses to delete the last admin and allows deleting one of several', function (): void {
    $actor = User::factory()->create();
    $action = app(DeleteUser::class);

    expect(fn () => $action->handle($actor, $this->admin))->toThrow(ValidationException::class, 'The last admin cannot be deleted.');
    expect($this->admin->fresh())->not->toBeNull();

    $second = User::factory()->withRole('admin')->create();
    $action->handle($actor, $second);
    expect($second->fresh())->toBeNull();
});

it('shows the linked account delete page only when deletable', function (): void {
    $user = User::factory()->create();
    $only = linkAccount($user);

    $this->actingAs($this->admin)->get(route('admin.users.accounts.delete', [$user, $only]))
        ->assertRedirect(route('admin.users.show', $user))->assertSessionHas('error');

    $second = linkAccount($user);
    $this->withoutVite()->actingAs($this->admin)->get(route('admin.users.accounts.delete', [$user, $second]))
        ->assertInertia(fn (Assert $page): Assert => $page->component('Admin/LinkedAccounts/Delete')->where('account.id', $second->id));
});

it('deletes a deletable linked account with audit and blocks the only one', function (): void {
    $user = User::factory()->create();
    $first = linkAccount($user);
    $second = linkAccount($user);

    $this->actingAs($this->admin)->delete(route('admin.users.accounts.destroy', [$user, $second]))
        ->assertRedirect(route('admin.users.show', $user))->assertSessionHas('success');
    expect(LinkedAccount::query()->find($second->id))->toBeNull()
        ->and(AdminAuditEntry::query()->where('action', 'linked_account.deleted')->count())->toBe(1);

    $this->actingAs($this->admin)->from('/x')->delete(route('admin.users.accounts.destroy', [$user, $first]))->assertSessionHasErrors('admin');
    expect(LinkedAccount::query()->find($first->id))->not->toBeNull();
});

it('does not resolve an account belonging to another user', function (): void {
    $user = User::factory()->create();
    $foreign = linkAccount();

    $this->actingAs($this->admin)->delete(route('admin.users.accounts.destroy', [$user, $foreign]))->assertNotFound();
});

it('impersonates a user, shares the banner prop and stops back to the admin', function (): void {
    $user = User::factory()->create(['nickname' => 'victim']);

    $this->withoutVite()->actingAs($this->admin)->post(route('admin.users.impersonate', $user))->assertRedirect('/');
    $this->assertAuthenticatedAs($user);
    expect(session('impersonator_id'))->toBe($this->admin->id);
    $this->get('/admin')->assertForbidden();

    $this->delete(route('admin.impersonate.stop'))->assertRedirect(route('admin.users.show', $user));
    $this->assertAuthenticatedAs($this->admin);
    expect(session()->has('impersonator_id'))->toBeFalse();
    expect(AdminAuditEntry::query()->pluck('action')->all())->toBe(['impersonation.started', 'impersonation.stopped'])
        ->and(AdminAuditEntry::query()->pluck('admin_id')->unique()->all())->toBe([$this->admin->id])
        ->and(AdminAuditEntry::query()->pluck('subject_user_id')->unique()->all())->toBe([$user->id]);
});

it('shares the impersonating prop while impersonating', function (): void {
    $user = User::factory()->create(['nickname' => 'victim']);
    $this->actingAs($this->admin)->post(route('admin.users.impersonate', $user));

    $this->withoutVite()->get('/')->assertInertia(fn (Assert $page): Assert => $page
        ->where('impersonating.name', 'victim')
        ->where('impersonating.stopUrl', '/admin/impersonate'));
});

it('refuses to impersonate self, suspended users, other admins or while already impersonating', function (): void {
    $suspended = User::factory()->suspended()->create();
    $otherAdmin = User::factory()->withRole('admin')->create();

    foreach ([$this->admin, $suspended, $otherAdmin] as $target) {
        $this->actingAs($this->admin)->from('/x')->post(route('admin.users.impersonate', $target))->assertSessionHasErrors('admin');
        $this->assertAuthenticatedAs($this->admin);
    }
    expect(AdminAuditEntry::query()->count())->toBe(0);

    $plain = User::factory()->create();
    $this->actingAs($this->admin)->post(route('admin.users.impersonate', $plain));
    $this->post(route('admin.users.impersonate', User::factory()->create()))->assertForbidden();
});

it('treats stop as a no-op when not impersonating', function (): void {
    $this->actingAs($this->admin)->delete(route('admin.impersonate.stop'))->assertRedirect('/');
    $this->assertAuthenticatedAs($this->admin);
    expect(AdminAuditEntry::query()->count())->toBe(0);
});

it('logs out when the impersonating admin is no longer an admin', function (): void {
    $user = User::factory()->create();
    $this->actingAs($this->admin)->post(route('admin.users.impersonate', $user));
    $this->admin->roles()->detach();

    $this->delete(route('admin.impersonate.stop'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('forbids non-admins from starting impersonation', function (): void {
    $this->actingAs(User::factory()->create())->post(route('admin.users.impersonate', User::factory()->create()))->assertForbidden();
});

it('gives the first user a single admin role row and shows it once', function (): void {
    $this->seed(RolesSeeder::class);
    $admin = User::factory()->withRole('admin')->create();

    expect($admin->roles()->count())->toBe(1);

    $this->actingAs($admin)->get(route('admin.users.show', $admin))
        ->assertInertia(fn (Assert $page): Assert => $page->has('user.roles', 1));
});

it('rejects a duplicate role assignment at the database level', function (): void {
    $admin = adminUser();
    $roleId = $admin->roles()->firstOrFail()->id;

    expect(fn () => DB::table('role_user')->insert(['user_id' => $admin->id, 'role_id' => $roleId]))
        ->toThrow(QueryException::class);
});

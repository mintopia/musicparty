<?php

use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Role;
use App\Models\SocialProvider;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $role = Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']);
    $this->admin = User::factory()->create(['first_login' => false]);
    $this->admin->roles()->attach($role);
    $this->target = User::factory()->create(['nickname' => 'Target', 'first_login' => false]);
});

it('keeps non-admins and guests out of every admin user route', function (string $method, string $route) {
    $account = LinkedAccount::factory()->create(['user_id' => $this->target->id]);
    $url = route($route, ['user' => $this->target, 'account' => $account]);

    $this->{$method}($url)->assertRedirect();
    $this->actingAs(User::factory()->create(['first_login' => false]))->{$method}($url)->assertForbidden();
})->with([
    ['get', 'admin.users.index'],
    ['get', 'admin.users.show'],
    ['get', 'admin.users.edit'],
    ['get', 'admin.users.delete'],
    ['get', 'admin.users.impersonate'],
    ['patch', 'admin.users.update'],
    ['delete', 'admin.users.destroy'],
    ['get', 'admin.users.accounts.delete'],
    ['delete', 'admin.users.accounts.destroy'],
]);

it('lists, filters and sorts users', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['q' => 'nickname:Targ', 'order' => 'nickname', 'order_direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->where('filters.q', 'nickname:Targ')
            ->where('order', 'nickname')
            ->where('order_direction', 'desc')
            ->has('users.data', 1)
            ->where('users.data.0.nickname', 'Target')
            ->where('users.data.0.last_login', null)
            ->has('users.data.0.avatar_url')
            ->has('users.data.0.accounts')
            ->where('users.data.0.parties_count', 0));
});

it('sorts by last login newest first with never-logged-in users last', function () {
    $recent = User::factory()->create(['nickname' => 'Recent', 'last_login' => now()->subMinute()]);
    $older = User::factory()->create(['nickname' => 'Older', 'last_login' => now()->subDays(3)]);

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('order', 'last_login')
            ->where('order_direction', 'desc')
            ->where('users.data.0.id', $recent->id)
            ->where('users.data.1.id', $older->id)
            ->where('users.data.2.last_login', null)
            ->where('users.data.3.last_login', null));
});

it('only offers login providers as filter chips', function () {
    SocialProvider::factory()->create(['code' => 'discord', 'name' => 'Discord', 'supports_auth' => true]);
    SocialProvider::factory()->create(['code' => 'passport', 'name' => 'Laravel Passport', 'supports_auth' => false]);

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('providers', fn ($providers) => collect($providers)->pluck('code')->all() === ['discord']));
});

it('searches by id, free text and the chips, and still honours the legacy id and nickname parameters', function () {
    $this->target->forceFill(['suspended' => true])->save();
    $account = LinkedAccount::factory()->create(['user_id' => $this->target->id]);
    $code = $account->provider->code;
    $ids = fn (array $query) => collect($this->actingAs($this->admin)->get(route('admin.users.index', $query))->assertOk()->viewData('page')['props']['users']['data'])->pluck('id')->all();

    expect($ids(['q' => (string) $this->target->id]))->toBe([$this->target->id])
        ->and($ids(['q' => 'id:'.$this->admin->id]))->toBe([$this->admin->id])
        ->and($ids(['q' => 'Targ']))->toBe([$this->target->id])
        ->and($ids(['id' => $this->target->id]))->toBe([$this->target->id])
        ->and($ids(['nickname' => 'Targ']))->toBe([$this->target->id])
        ->and($ids(['role' => 'admin']))->toBe([$this->admin->id])
        ->and($ids(['suspended' => 1]))->toBe([$this->target->id])
        ->and($ids(['provider' => $code]))->toBe([$this->target->id])
        ->and($ids(['first_login' => 1]))->toBe([])
        ->and($ids(['spotify_relink' => 1]))->toBe([]);

    $spotify = SocialProvider::factory()->create(['code' => 'spotify']);
    LinkedAccount::factory()->create(['user_id' => $this->target->id, 'social_provider_id' => $spotify->id, 'needs_relink_at' => now()]);
    expect($ids(['spotify_relink' => 1]))->toBe([$this->target->id]);

    $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'admin']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 2)->where('users.data.0.is_admin', true)->has('providers'));
});

it('shows a user with linked accounts', function () {
    $account = LinkedAccount::factory()->create(['user_id' => $this->target->id]);

    $this->actingAs($this->admin)->get(route('admin.users.show', $this->target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Show')
            ->where('user.id', $this->target->id)
            ->where('user.suspended', false)
            ->has('accounts', 1)
            ->where('accounts.0.id', $account->id)
            ->where('accounts.0.can_delete', false)
            ->where('accounts.0.needs_relink', false)
            ->missing('accounts.0.token_expires_human')
            ->missing('accounts.0.token_expired')
            ->has('accounts.0.linked_human')
            ->has('user.avatar_url')
            ->where('parties', []));
});

it('shows link state and party memberships on the user page', function () {
    $spotify = SocialProvider::factory()->create(['code' => 'spotify']);
    LinkedAccount::factory()->create([
        'user_id' => $this->target->id,
        'social_provider_id' => $spotify->id,
        'needs_relink_at' => now(),
    ]);
    $party = Party::factory()->create(['name' => 'Retro Room']);
    PartyMember::factory()->create(['user_id' => $this->target->id, 'party_id' => $party->id]);

    $this->actingAs($this->admin)->get(route('admin.users.show', $this->target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.0.needs_relink', true)
            ->where('parties.0.name', 'Retro Room')
            ->where('parties.0.code', $party->code)
            ->has('parties.0.role'));
});

it('renders the edit form and updates the user', function () {
    $this->actingAs($this->admin)->get(route('admin.users.edit', $this->target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Edit')
            ->where('user.nickname', 'Target')
            ->where('user.first_login_completed', true)
            ->has('roles'));

    $this->actingAs($this->admin)
        ->patch(route('admin.users.update', $this->target), ['nickname' => 'Renamed', 'roles' => ['admin'], 'suspended' => 1, 'first_login' => 1])
        ->assertRedirect(route('admin.users.show', $this->target))
        ->assertSessionHas('successMessage');

    $fresh = $this->target->fresh();
    expect($fresh->nickname)->toBe('Renamed')
        ->and($fresh->suspended)->toBeTrue()
        ->and($fresh->roles->pluck('code')->all())->toBe(['admin']);
});

it('offers only the admin role and leaves other roles alone on update', function () {
    $other = Role::query()->forceCreate(['code' => 'create-party', 'name' => 'Create Party']);
    $this->target->roles()->attach($other);

    $this->actingAs($this->admin)->get(route('admin.users.edit', $this->target))
        ->assertInertia(fn (Assert $page) => $page->has('roles', 1)->where('roles.0.code', 'admin'));

    $this->actingAs($this->admin)
        ->patch(route('admin.users.update', $this->target), ['nickname' => 'Target', 'roles' => ['admin', 'create-party']])
        ->assertRedirect();

    expect($this->target->fresh()->roles->pluck('code')->sort()->values()->all())->toBe(['admin', 'create-party']);
});

it('validates the update', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.users.update', $this->target), ['nickname' => ''])
        ->assertSessionHasErrors('nickname');
});

it('confirms before deleting and requires the confirm word', function () {
    $this->actingAs($this->admin)->get(route('admin.users.delete', $this->target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Delete')
            ->where('user.id', $this->target->id)
            ->where('impact.accounts_count', 0)
            ->where('impact.memberships_count', 0)
            ->where('impact.owned_parties', []));

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->target), ['confirm' => 'nope'])
        ->assertSessionHasErrors('confirm');
    expect($this->target->fresh())->not->toBeNull();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->target), ['confirm' => 'delete'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('successMessage');
    expect(User::find($this->target->id))->toBeNull();
});

it('summarises what deleting a user removes', function () {
    LinkedAccount::factory()->count(2)->create(['user_id' => $this->target->id]);
    $owned = Party::factory()->create(['user_id' => $this->target->id, 'name' => 'Retro Room']);
    PartyMember::factory()->create(['user_id' => $this->target->id, 'party_id' => $owned->id]);

    $this->actingAs($this->admin)->get(route('admin.users.delete', $this->target))
        ->assertInertia(fn (Assert $page) => $page
            ->where('impact.accounts_count', 2)
            ->has('impact.accounts', 2)
            ->where('impact.memberships_count', 1)
            ->where('impact.owned_parties', ['Retro Room']));
});

it('confirms and deletes a linked account when another remains', function () {
    [$keep, $account] = LinkedAccount::factory()->count(2)->create(['user_id' => $this->target->id]);

    $this->actingAs($this->admin)->get(route('admin.users.accounts.delete', [$this->target, $account]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/LinkedAccounts/Delete')
            ->where('user.id', $this->target->id)
            ->where('account.id', $account->id));

    $this->actingAs($this->admin)->delete(route('admin.users.accounts.destroy', [$this->target, $account]))
        ->assertRedirect(route('admin.users.show', $this->target))
        ->assertSessionHas('successMessage');
    expect(LinkedAccount::find($account->id))->toBeNull()->and(LinkedAccount::find($keep->id))->not->toBeNull();
});

it('refuses to delete the only linked account', function () {
    $account = LinkedAccount::factory()->create(['user_id' => $this->target->id]);

    $this->actingAs($this->admin)->get(route('admin.users.accounts.delete', [$this->target, $account]))
        ->assertRedirect(route('admin.users.show', $this->target))
        ->assertSessionHas('errorMessage');
    $this->actingAs($this->admin)->delete(route('admin.users.accounts.destroy', [$this->target, $account]))
        ->assertRedirect(route('admin.users.show', $this->target))
        ->assertSessionHas('errorMessage');
    expect(LinkedAccount::find($account->id))->not->toBeNull();
});

it('impersonates a user', function () {
    $this->actingAs($this->admin)->get(route('admin.users.impersonate', $this->target))
        ->assertRedirect(route('home'))
        ->assertSessionHas('impersonating', true)
        ->assertSessionHas('originalUserId', $this->admin->id);

    expect(auth('web')->id())->toBe($this->target->id);
});

it('leaves impersonation back to the impersonated user page', function () {
    $this->actingAs($this->target)
        ->withSession(['impersonating' => true, 'originalUserId' => $this->admin->id])
        ->get(route('admin.unimpersonate'))
        ->assertRedirect(route('admin.users.show', $this->target))
        ->assertSessionMissing('impersonating');

    expect(auth('web')->id())->toBe($this->admin->id);
});

it('forbids leaving impersonation when not impersonating', function () {
    $this->actingAs($this->admin)->get(route('admin.unimpersonate'))->assertForbidden();
});

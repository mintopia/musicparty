<?php

use App\Models\LinkedAccount;
use App\Models\Role;
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
            ->where('users.data.0.last_login', null));
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
        ->and($ids(['first_login' => 1]))->toBe([]);

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
            ->where('accounts.0.can_delete', false));
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

it('validates the update', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.users.update', $this->target), ['nickname' => ''])
        ->assertSessionHasErrors('nickname');
});

it('confirms before deleting and requires the confirm word', function () {
    $this->actingAs($this->admin)->get(route('admin.users.delete', $this->target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Delete')->where('user.id', $this->target->id));

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->target), ['confirm' => 'nope'])
        ->assertSessionHasErrors('confirm');
    expect($this->target->fresh())->not->toBeNull();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->target), ['confirm' => 'delete'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('successMessage');
    expect(User::find($this->target->id))->toBeNull();
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

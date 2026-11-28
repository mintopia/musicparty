<?php

use App\Events\UserSuspended;
use App\Models\ModData;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Role;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->withoutVite();
    $this->adminRole = Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']);
    $this->admin = User::factory()->create(['first_login' => false]);
    $this->admin->roles()->attach($this->adminRole);
});

it('ends the session and returns 403 on the next web request after suspension', function () {
    $user = User::factory()->create(['first_login' => false]);
    $this->actingAs($user)->get(route('home'))->assertOk();

    $user->forceFill(['suspended' => true])->save();

    $this->get(route('home'))->assertForbidden();
    $this->assertGuest();
});

it('returns 403 on the next api request after suspension', function () {
    $user = User::factory()->create(['suspended' => true]);

    $this->actingAs($user)->getJson(route('api.v1.ping'))->assertForbidden();
    $this->assertGuest();
});

it('does not log out a suspended user being impersonated', function () {
    $target = User::factory()->create(['suspended' => true, 'first_login' => false]);

    $this->actingAs($target)
        ->withSession(['impersonating' => true, 'originalUserId' => $this->admin->id])
        ->get(route('home'))
        ->assertOk();
    $this->assertAuthenticatedAs($target);
});

it('only logs out through POST', function () {
    $user = User::factory()->create(['first_login' => false]);

    $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
});

it('only impersonates through POST', function () {
    $target = User::factory()->create(['first_login' => false]);

    $this->actingAs($this->admin)->get("/admin/users/{$target->id}/impersonate")->assertMethodNotAllowed();
    $this->assertAuthenticatedAs($this->admin);
});

it('refuses to delete a user who owns parties', function () {
    $owner = User::factory()->create(['first_login' => false]);
    $party = Party::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $owner), ['confirm' => 'delete'])
        ->assertRedirect()
        ->assertSessionHas('errorMessage');

    expect($owner->fresh())->not->toBeNull()->and($party->fresh())->not->toBeNull();
    $this->get(route('admin.users.delete', $owner))
        ->assertInertia(fn ($page) => $page->whereNot('blocked_reason', null));
});

it('refuses to delete yourself or the last admin', function () {
    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin), ['confirm' => 'delete'])
        ->assertSessionHas('errorMessage');
    expect($this->admin->fresh())->not->toBeNull();

    $second = User::factory()->create(['first_login' => false]);
    $second->roles()->attach($this->adminRole);
    $this->admin->roles()->detach($this->adminRole);
    Gate::before(fn () => true);
    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $second), ['confirm' => 'delete'])
        ->assertSessionHas('errorMessage');
    expect($second->fresh())->not->toBeNull();

    $this->admin->roles()->attach($this->adminRole);
    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $second), ['confirm' => 'delete'])
        ->assertSessionHas('successMessage');
    expect($second->fresh())->toBeNull();
});

it('removes mod data when a user or party is deleted', function () {
    $user = User::factory()->create(['first_login' => false]);
    $party = Party::factory()->create();
    $member = PartyMember::factory()->create(['user_id' => $user->id, 'party_id' => $party->id]);
    foreach ([$user, $member, $party] as $owner) {
        ModData::create(['mod_code' => 'trust', 'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'key' => 'k', 'value' => 1]);
    }

    $user->delete();
    expect(ModData::count())->toBe(1);

    $party->delete();
    expect(ModData::count())->toBe(0);
});

it('gives only one of two simultaneous first sign-ins the admin role', function () {
    $this->adminRole->users()->detach();
    $users = User::withoutEvents(fn () => User::factory()->count(2)->create());

    $users->each(fn (User $user) => app(UserObserver::class)->created($user));

    expect($this->adminRole->users()->count())->toBe(1)
        ->and($users->first()->hasRole('admin'))->toBeTrue()
        ->and($users->last()->hasRole('admin'))->toBeFalse();
});

it('refuses to delete the last admin with an accurate message', function () {
    $other = User::factory()->create(['first_login' => false]);
    $other->grantRole($this->adminRole);
    $this->admin->revokeRole($this->adminRole);
    Gate::before(fn () => true);

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $other), ['confirm' => 'delete'])
        ->assertSessionHas('errorMessage', 'The last admin cannot be deleted. Make another user an admin first.');
    expect($other->fresh())->not->toBeNull();
});

it('purges mod data of a user and their memberships in one query', function () {
    $user = User::factory()->create(['first_login' => false]);
    $members = PartyMember::factory()->count(3)->create(['user_id' => $user->id]);
    foreach ([$user, ...$members] as $owner) {
        ModData::create(['mod_code' => 'trust', 'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'key' => 'k', 'value' => 1]);
    }
    $user->load('partyMembers');

    $deletes = 0;
    DB::listen(function ($query) use (&$deletes) {
        if (str_starts_with($query->sql, 'delete from `mod_data`') || str_starts_with($query->sql, 'delete from "mod_data"')) {
            $deletes++;
        }
    });
    $user->delete();

    expect($deletes)->toBe(1)->and(ModData::count())->toBe(0);
});

it('broadcasts a suspension to the user only when they become suspended', function () {
    Event::fake([UserSuspended::class]);
    $user = User::factory()->create(['first_login' => false]);

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), ['nickname' => $user->nickname, 'suspended' => true, 'first_login' => true]);
    Event::assertDispatched(UserSuspended::class, fn (UserSuspended $event) => $event->userId === $user->id);

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), ['nickname' => $user->nickname, 'suspended' => true, 'first_login' => true]);
    Event::assertDispatchedTimes(UserSuspended::class, 1);
});

it('reflects a role change immediately on the same user instance', function () {
    $user = User::factory()->create(['first_login' => false]);
    expect($user->hasRole('admin'))->toBeFalse();

    $user->grantRole($this->adminRole);
    expect($user->hasRole('admin'))->toBeTrue();

    $user->revokeRole($this->adminRole);
    expect($user->hasRole('admin'))->toBeFalse();
});

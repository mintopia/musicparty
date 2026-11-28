<?php

use App\Models\ModData;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Role;
use App\Models\User;
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

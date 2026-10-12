<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

function partyWithMember(string $roleCode): array
{
    $host = User::factory()->create();
    $party = new Party;
    $party->code = 'ABCD';
    $party->name = 'Test Party';
    $party->user_id = $host->id;
    Party::withoutEvents(fn () => $party->save());

    $member = User::factory()->create();
    $role = PartyMemberRole::query()->forceCreate(['code' => $roleCode, 'name' => ucfirst($roleCode)]);
    $row = new PartyMember;
    $row->party_id = $party->id;
    $row->user_id = $member->id;
    $row->role_id = $role->id;
    $row->save();

    return [$party, $member];
}

it('forbids a manager without the owner role from the web player', function () {
    [$party, $member] = partyWithMember('vip');

    $this->actingAs($member)->get(route('parties.player', $party))->assertForbidden();
});

it('forbids a guest from the web player', function () {
    [$party] = partyWithMember('owner');

    $this->get(route('parties.player', $party))->assertRedirect();
});

it('lets an owner-role member open the web player', function () {
    $this->withoutVite();
    [$party, $member] = partyWithMember('owner');

    $this->actingAs($member)->get(route('parties.player', $party))->assertOk();
});

it('lets an instance admin open the web player', function () {
    $this->withoutVite();
    Route::get('/telescope', fn () => '')->name('telescope');
    Route::getRoutes()->refreshNameLookups();
    [$party] = partyWithMember('user');
    $admin = User::factory()->create();
    $role = Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']);
    $admin->roles()->attach($role);

    $this->actingAs($admin)->get(route('parties.player', $party))->assertOk();
});

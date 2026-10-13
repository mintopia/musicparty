<?php

use App\Enums\SettingType;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the Shell page with guest shared props', function () {
    $this->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shell')
            ->where('auth.user', null)
            ->where('auth.parties', [])
            ->has('flash')
            ->has('reverb.appKey')
            ->has('instanceName'));
});

it('shares the signed-in user and party memberships', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create(['user_id' => $user->id]);
    PartyMember::factory()->create(['party_id' => $party->id, 'user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.nickname', $user->nickname)
            ->where('auth.user.impersonating', false)
            ->has('auth.user.avatar')
            ->has('auth.user.roles')
            ->where('auth.parties.0.code', $party->code)
            ->where('auth.parties.0.canManage', true));
});

it('reads the Reverb key from config', function () {
    config(['reverb.apps.apps.0.key' => 'party-key']);

    $this->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page->where('reverb.appKey', 'party-key'));
});

it('shares navigation abilities, site settings and flash levels', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::forceCreate(['code' => 'admin', 'name' => 'Admin']));

    $this->actingAs($admin, 'sanctum')
        ->withSession(['infoMessage' => 'Heads up', 'warningMessage' => 'Careful'])
        ->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.admin', true)
            ->where('auth.can.createParty', true)
            ->where('flash.info', 'Heads up')
            ->where('flash.warning', 'Careful')
            ->where('theme.darkMode', false)
            ->has('site.defaultParty'));
});

it('does not grant admin or party creation to ordinary members', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.admin', false)
            ->where('auth.can.createParty', false));
});

it('flags an impersonated session', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->withSession(['impersonating' => true])
        ->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.impersonating', true));
});

it('shares the site settings and instance name', function () {
    Setting::query()->where('code', 'name')->delete();
    Setting::query()->forceCreate(['code' => 'name', 'name' => 'Name', 'type' => SettingType::stString, 'value' => 'LAN Fest']);

    $this->get(route('inertia.shell'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('instanceName', 'LAN Fest')
            ->has('site.logoLight')
            ->has('site.logoDark')
            ->has('site.terms')
            ->has('site.privacyPolicy')
            ->has('site.coverImage')
            ->has('site.favicon'));
});

it('shares empty validation errors, and the errors after a failed submission', function () {
    $this->get(route('inertia.shell'))->assertInertia(fn (Assert $page) => $page->where('errors', []));

    $admin = User::factory()->create();
    $admin->roles()->attach(Role::forceCreate(['code' => 'admin', 'name' => 'Admin']));

    $this->actingAs($admin)
        ->from(route('inertia.shell'))
        ->followingRedirects()
        ->post(route('admin.settings.themes.store'), ['name' => '!!!'])
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shell')
            ->has('errors', fn (Assert $errors) => $errors->has('name')->has('tokens.primary')->etc()));
});

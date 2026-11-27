<?php

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PartyMemberRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

it('lists only the parties the member owns', function () {
    $user = User::factory()->create(['first_login' => false]);
    $mine = Party::factory()->create(['user_id' => $user->id, 'name' => 'Mine']);
    Party::factory()->create(['name' => 'Someone Else']);

    $this->actingAs($user)->get('/parties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Index', false)
            ->has('parties', 1)
            ->where('parties.0.code', $mine->code)
            ->where('parties.0.role', 'Owner')
            ->where('parties.0.canManage', true));
});

it('lists a joined party with the member role and the Queue link', function () {
    $this->seed(PartyMemberRoleSeeder::class);
    $user = User::factory()->create(['first_login' => false]);
    $party = Party::factory()->create(['name' => 'Joined']);
    PartyMember::factory()->for($party)->for($user)
        ->create(['role_id' => PartyMemberRole::whereCode('user')->firstOrFail()->id]);

    $this->actingAs($user)->get('/parties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('parties', 1)
            ->where('parties.0.code', $party->code)
            ->where('parties.0.roleCode', 'user')
            ->where('parties.0.canManage', false)
            ->where('parties.0.playback', 'idle')
            ->where('parties.0.url', route('parties.show', $party->code)));
});

it('renders an empty list for a member with no parties and for admins', function (bool $admin) {
    $user = User::factory()->create(['first_login' => false]);
    if ($admin) {
        $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
    }

    $this->actingAs($user)->get('/parties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('parties', 0));
})->with([[false], [true]]);

it('does not error for guests on /parties', function () {
    expect($this->get('/parties')->status())->toBeLessThan(500);
});

it('points no registered route at a missing controller method', function () {
    $missing = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getAction('uses'))
        ->filter(fn ($uses) => is_string($uses) && str_contains($uses, '@'))
        ->reject(fn ($uses) => method_exists(...explode('@', $uses)))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

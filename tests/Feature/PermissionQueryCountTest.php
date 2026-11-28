<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\User;
use Database\Seeders\PartyMemberRoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Support/DashboardFixtures.php';

beforeEach(function () {
    $this->withoutVite();
});

function countQueries(Closure $visit): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $visit();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

function userInParties(int $count): User
{
    test()->seed(PartyMemberRoleSeeder::class);
    $user = User::factory()->create(['first_login' => false]);
    $roles = ['owner', 'moderator', 'user', 'vip', 'moderator'];
    foreach (range(0, $count - 1) as $i) {
        $party = Party::factory()->create(['provider_id' => 'spotify']);
        $member = new PartyMember;
        $member->party_id = $party->id;
        $member->user_id = $user->id;
        $member->role_id = PartyMemberRole::query()->where('code', $roles[$i % count($roles)])->firstOrFail()->id;
        $member->save();
    }

    return $user;
}

it('runs the same number of queries for a member of one Party and of five', function () {
    $one = userInParties(1);
    $five = userInParties(5);

    $visit = fn (User $user) => fn () => $this->actingAs($user)->get(route('parties.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('auth.parties'));

    $this->actingAs($one)->get(route('parties.index'));
    $this->actingAs($five)->get(route('parties.index'));

    expect(countQueries($visit($five)))->toBe(countQueries($visit($one)));
});

it('shares manage and moderate flags from the member rows', function () {
    $user = userInParties(3);

    $this->actingAs($user)->get(route('parties.index'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.parties.0.canManage', true)
        ->where('auth.parties.0.canModerate', true)
        ->where('auth.parties.1.canManage', false)
        ->where('auth.parties.1.canModerate', true)
        ->where('auth.parties.2.canModerate', false));
});

it('keeps Dashboard load within its query budget', function () {
    $this->fake = dashboardFake();
    $party = dashboardParty();
    dashboardRequest($party);
    dashboardRequest($party, ['queued_at' => now()]);
    $url = route('parties.dashboard', $party->code);

    $this->actingAs($party->user)->get($url)->assertOk();

    expect(countQueries(fn () => $this->actingAs($party->user)->get($url)->assertOk()))->toBeLessThanOrEqual(55);
});

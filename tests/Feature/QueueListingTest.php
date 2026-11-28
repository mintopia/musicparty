<?php

use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Mods\PartyMods;
use App\Mods\Trust\TrustMod;
use App\Services\ModDataStore;
use App\Services\Queue\QueueListing;
use App\Services\QueueSelector;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Random\Engine\Mt19937;
use Random\Randomizer;

require_once __DIR__.'/../Support/DashboardFixtures.php';

function queryCount(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function trustedQueue(): array
{
    $party = dashboardParty(['weighted' => false]);
    Artisan::call('mods:sync');
    app(PartyMods::class)->enable($party, TrustMod::CODE);
    $trusted = dashboardUser($party, 'user');
    $plain = dashboardUser($party, 'user');
    $member = PartyMember::query()->where('party_id', $party->id)->where('user_id', $trusted->id)->firstOrFail();
    app(ModDataStore::class)->set($member, TrustMod::CODE, 'trustscore', 1.0);
    $low = dashboardRequest($party, ['user_id' => $plain->id, 'score' => 1, 'queued_at' => null]);
    $high = dashboardRequest($party, ['user_id' => $trusted->id, 'score' => 0, 'queued_at' => null]);

    return [$party, $low, $high];
}

beforeEach(function () {
    $this->withoutVite();
    app()->instance(Randomizer::class, new Randomizer(new Mt19937(7)));
});

it('runs a bounded number of queries when picking regardless of queue size', function () {
    $party = dashboardParty(['weighted' => true]);
    Artisan::call('mods:sync');
    app(PartyMods::class)->enable($party, TrustMod::CODE);
    $user = dashboardUser($party, 'user');
    UpcomingSong::factory()->count(5)->create(['party_id' => $party->id, 'user_id' => $user->id, 'score' => 3, 'queued_at' => null]);
    app(QueueSelector::class)->pick($party, 1);
    $small = queryCount(fn () => app(QueueSelector::class)->pick($party, 1));

    UpcomingSong::factory()->count(195)->create(['party_id' => $party->id, 'user_id' => $user->id, 'score' => 3, 'queued_at' => null]);
    $large = queryCount(fn () => app(QueueSelector::class)->pick($party, 1));

    expect($large)->toBe($small)->and($large)->toBeLessThan(20);
});

it('lists a page with a fixed query count regardless of queue size', function () {
    $party = dashboardParty();
    $user = dashboardUser($party, 'user');
    foreach (range(1, 20) as $i) {
        dashboardRequest($party, ['user_id' => $user->id, 'queued_at' => null]);
    }
    app(QueueListing::class)->page($party, 0, 20);
    $small = queryCount(fn () => app(QueueListing::class)->page($party, 0, 20));

    foreach (range(1, 40) as $i) {
        dashboardRequest($party, ['user_id' => $user->id, 'queued_at' => null]);
    }
    $page = null;
    $large = queryCount(function () use ($party, &$page): void {
        $page = app(QueueListing::class)->page($party, 0, 20);
    });

    expect($page['items'])->toHaveCount(20)->and($page['total'])->toBe(60)->and($large)->toBe($small);
});

it('lists requests in the same order the selector ranks them with a trust bonus', function () {
    [$party, $low, $high] = trustedQueue();

    $listed = app(QueueListing::class)->page($party, 0, 10)['items']->pluck('id')->all();
    $picked = app(QueueSelector::class)->pick($party, 2)->pluck('id')->all();

    expect($listed)->toBe([$high->id, $low->id])->and($picked)->toBe($listed);
});

it('flags the first selectable request as next and held requests as held', function () {
    $party = dashboardParty();
    $held = dashboardRequest($party, ['score' => 50, 'queued_at' => null, 'not_before' => now()->addHour()]);
    $first = dashboardRequest($party, ['score' => 2, 'queued_at' => null]);
    $second = dashboardRequest($party, ['score' => 1, 'queued_at' => null]);

    $page = app(QueueListing::class)->page($party, 0, 10);

    expect($page['items']->pluck('id')->all())->toBe([$first->id, $second->id, $held->id])
        ->and($page['states'][$first->id])->toBe(['held' => false, 'next' => true])
        ->and($page['states'][$second->id])->toBe(['held' => false, 'next' => false])
        ->and($page['states'][$held->id])->toBe(['held' => true, 'next' => false]);
});

it('serves the api listing in selector order and rejects an oversized per_page', function () {
    [$party, $low, $high] = trustedQueue();
    $viewer = dashboardUser($party, 'user');
    Sanctum::actingAs($viewer);

    $response = $this->getJson(route('api.v1.parties.upcomingsongs.index', [$party, 'per_page' => 1]))->assertSuccessful();
    expect($response->json('data.0.id'))->toBe($high->id)->and($response->json('data.0.next'))->toBeTrue();

    $this->getJson(route('api.v1.parties.upcomingsongs.index', [$party, 'per_page' => 1000]))->assertJsonValidationErrors('per_page');
    $this->getJson(route('api.v1.parties.upcomingsongs.index', [$party, 'per_page' => 0]))->assertJsonValidationErrors('per_page');
});

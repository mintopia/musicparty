<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($this->user)->create();
    Redis::connection()->del("search:{$this->user->id}");
    Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));
});

afterEach(function () {
    Redis::connection()->del("search:{$this->user->id}");
    Carbon::setTestNow();
});

function apiSearch(): TestResponse
{
    return test()->getJson('/api/v1/parties/ABCD/search?q=song');
}

it('admits a burst of 30 searches and refuses the 31st with Retry-After', function () {
    Sanctum::actingAs($this->user);

    foreach (range(1, 30) as $attempt) {
        apiSearch()->assertOk();
    }

    apiSearch()->assertStatus(429)->assertHeader('Retry-After', '1');
});

it('admits one more search after a second', function () {
    Sanctum::actingAs($this->user);
    foreach (range(1, 30) as $attempt) {
        apiSearch()->assertOk();
    }
    apiSearch()->assertStatus(429);

    Carbon::setTestNow(Carbon::now()->addSecond());

    apiSearch()->assertOk();
    apiSearch()->assertStatus(429);
});

it('shares the bucket between web and API search', function () {
    foreach (range(1, 29) as $attempt) {
        $this->actingAs($this->user)->getJson('/api/v1/parties/ABCD/search?q=song')->assertOk();
    }
    $this->actingAs($this->user)->get('/parties/ABCD/search?q=song')->assertOk();

    apiSearch()->assertStatus(429);

    $this->actingAs($this->user)->get('/parties/ABCD/search?q=song')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('results', [])
            ->where('search_error', fn (string $message): bool => str_contains($message, 'Try again in 1 second'))
            ->has('queue'));
});

it('does not affect another user', function () {
    Sanctum::actingAs($this->user);
    foreach (range(1, 31) as $attempt) {
        apiSearch();
    }
    apiSearch()->assertStatus(429);

    $other = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($other)->create();
    Redis::connection()->del("search:{$other->id}");
    Sanctum::actingAs($other);

    apiSearch()->assertOk();
    Redis::connection()->del("search:{$other->id}");
});

it('does not spend allowance on non-members', function () {
    $outsider = User::factory()->create();
    Redis::connection()->del("search:{$outsider->id}");
    Sanctum::actingAs($outsider);

    foreach (range(1, 35) as $attempt) {
        apiSearch()->assertForbidden();
    }
});

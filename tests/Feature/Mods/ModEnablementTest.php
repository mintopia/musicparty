<?php

use App\Enums\ScheduleFrequency;
use App\Models\Mod;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
use App\Mods\Foo\FooMod;
use App\Mods\ModRegistry;
use App\Mods\ModScheduler;
use App\Mods\PartyMods;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Requests\RequestRules;
use Illuminate\Support\Carbon;
use Tests\Support\FooModFixture;
use Tests\Support\WhamageddonFixtures;

beforeEach(function () {
    FooModFixture::bindRegistry();
    $this->artisan('mods:sync')->assertSuccessful();
    $this->partyA = Party::factory()->create();
    $this->partyB = Party::factory()->create();
    $this->mods = app(PartyMods::class);
});

afterEach(fn () => Carbon::setTestNow());

it('enables a mod for one party without affecting another', function () {
    $this->mods->enable($this->partyA, 'foo');

    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeTrue()
        ->and($this->mods->isEnabled($this->partyB, 'foo'))->toBeFalse();

    $this->mods->disable($this->partyA, 'foo');

    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeFalse();
});

it('invokes the onEnable and onDisable hooks once per transition', function () {
    $this->mods->enable($this->partyA, 'foo');
    $this->mods->enable($this->partyA, 'foo');
    $this->mods->disable($this->partyA, 'foo');
    $this->mods->disable($this->partyA, 'foo');

    expect(FooMod::$calls)->toBe(["enable:{$this->partyA->id}", "disable:{$this->partyA->id}"]);
});

it('sync enables listed mods and disables the rest', function () {
    $this->mods->enable($this->partyA, 'foo');
    FooMod::$calls = [];

    $this->mods->sync($this->partyA, []);
    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeFalse();

    $this->mods->sync($this->partyA, ['foo', 'unknown']);
    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeTrue()
        ->and(FooMod::$calls)->toBe(["disable:{$this->partyA->id}", "enable:{$this->partyA->id}"]);
});

it('enable is a no-op for unknown and removed mods', function () {
    $this->mods->enable($this->partyA, 'unknown');
    Mod::whereCode('foo')->update(['removed_at' => now()]);
    $this->mods->enable($this->partyA, 'foo');

    expect($this->partyA->mods()->count())->toBe(0)->and(FooMod::$calls)->toBe([]);
});

it('treats a removed mod as not enabled', function () {
    $this->mods->enable($this->partyA, 'foo');
    Mod::whereCode('foo')->update(['removed_at' => now()]);

    expect($this->mods->isEnabled($this->partyA, 'foo'))->toBeFalse();
});

it('has no effect on the hourly tick while disabled and does once enabled', function () {
    app()->instance(ModRegistry::class, tap(new ModRegistry(app_path('Mods')))->discover());
    WhamageddonFixtures::fake();
    WhamageddonFixtures::install();
    Carbon::setTestNow('2026-12-24 12:00:00');
    $party = WhamageddonFixtures::party();
    $upcoming = WhamageddonFixtures::upcoming($party);

    app(ModScheduler::class)->run(ScheduleFrequency::Hour);
    expect($upcoming->fresh()->score_adjustment)->toBe(0);

    app(PartyMods::class)->enable($party, 'whamageddon');
    app(ModScheduler::class)->run(ScheduleFrequency::Hour);
    expect($upcoming->fresh()->score_adjustment)->toBe(1);
});

describe('party mods screen', function () {
    beforeEach(function () {
        $this->withoutVite();
        $this->owner = User::factory()->withSpotify()->create();
        $this->owner->forceFill(['first_login' => false])->save();
        $this->party = Party::factory()->for($this->owner)->create();
    });

    it('toggles a mod through the Mods screen', function () {
        $this->actingAs($this->owner)->put(route('parties.mods.update', $this->party->code), ['mods' => ['foo' => ['enabled' => true]]])
            ->assertSessionHasNoErrors()->assertRedirect();
        expect(app(PartyMods::class)->isEnabled($this->party, 'foo'))->toBeTrue();

        $this->actingAs($this->owner)->put(route('parties.mods.update', $this->party->code), ['mods' => ['foo' => ['enabled' => false]]]);
        expect(app(PartyMods::class)->isEnabled($this->party, 'foo'))->toBeFalse();
    });

    it('lists mods on the Mods screen and hides removed mods', function () {
        Mod::factory()->create(['code' => 'gone', 'name' => 'Gone Mod', 'removed_at' => now()]);

        $this->actingAs($this->owner)->get(route('parties.mods.edit', $this->party->code))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Parties/Mods')->has('mods', 1)->where('mods.0.code', 'foo'));
    });
});

it('applies a mod request rule only to parties that enabled the mod', function () {
    require_once __DIR__.'/../../Fixtures/RuleMods/Gate/GateMod.php';
    $registry = new ModRegistry(__DIR__.'/../../Fixtures/RuleMods');
    $registry->discover();
    app()->instance(ModRegistry::class, $registry);
    $this->artisan('mods:sync')->assertSuccessful();
    $track = app(FakeMusicProvider::class)->getTrack('fake-track-1');
    $partyA = Party::factory()->create(['provider_id' => 'fake', 'allow_requests' => true, 'explicit' => true])->refresh();
    $partyB = Party::factory()->create(['provider_id' => 'fake', 'allow_requests' => true, 'explicit' => true])->refresh();
    $memberA = PartyMember::factory()->for($partyA)->for(User::factory())->create();
    $memberB = PartyMember::factory()->for($partyB)->for(User::factory())->create();
    $rules = fn () => app(RequestRules::class);

    app(PartyMods::class)->enable($partyA, 'gate');

    expect($rules()->check($partyA, $memberA, $track)->reason)->toBe('Gate says no')
        ->and($rules()->check($partyB, $memberB, $track)->allowed)->toBeTrue();

    app(PartyMods::class)->disable($partyA, 'gate');

    expect($rules()->check($partyA, $memberA, $track)->allowed)->toBeTrue();
});

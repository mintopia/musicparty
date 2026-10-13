<?php

use App\Events\Cron\HourTickEvent;
use App\Models\Mod;
use App\Models\Party;
use App\Models\User;
use App\Mods\PartyMods;
use App\Mods\Whamageddon\Listeners\HourTickListener;
use App\Services\ModSettingsStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeSpotify;
use Tests\Support\WhamageddonFixtures;

beforeEach(function () {
    $this->fake = WhamageddonFixtures::fake();
    WhamageddonFixtures::install();
});

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function whamTick(): void
{
    (new HourTickListener)->handle(new HourTickEvent);
}

it('registers only the hour tick listener', function () {
    $raw = Event::getRawListeners();

    expect(Event::hasListeners(HourTickEvent::class))->toBeTrue()
        ->and($raw[HourTickEvent::class])->toContain(HourTickListener::class);
});

it('enabling the mod for a party adds the configured track', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID]);

    app(PartyMods::class)->enable($party, 'whamageddon');

    expect(app(PartyMods::class)->isEnabled($party, 'whamageddon'))->toBeTrue()
        ->and($party->upcoming()->count())->toBe(1);
});

it('disabling the mod removes the upcoming song and its tracking setting', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID]);
    app(PartyMods::class)->enable($party, 'whamageddon');
    expect($party->upcoming()->count())->toBe(1);

    app(PartyMods::class)->disable($party, 'whamageddon');

    expect($party->upcoming()->count())->toBe(0)
        ->and(app(PartyMods::class)->isEnabled($party, 'whamageddon'))->toBeFalse()
        ->and(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'upcoming_song_id'))->toBe(0);
});

it('enabling an already enabled mod does not re-run the enable hook', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID]);
    app(PartyMods::class)->enable($party, 'whamageddon');
    $this->fake->respond('getTrack', FakeSpotify::track(WhamageddonFixtures::TRACK_ID, 'Last Christmas'));
    $calls = count($this->fake->callsTo('getTrack'));

    app(PartyMods::class)->enable($party, 'whamageddon');

    expect($party->upcoming()->count())->toBe(1)->and($this->fake->callsTo('getTrack'))->toHaveCount($calls);
});

it('hourly tick processes only active parties with the mod enabled', function () {
    Carbon::setTestNow('2026-12-24 12:00:00');
    $active = WhamageddonFixtures::party(['enabled' => true]);
    $inactive = WhamageddonFixtures::party(['enabled' => true], active: false);
    $disabled = WhamageddonFixtures::party();
    $upcomings = collect([$active, $inactive, $disabled])->map(fn (Party $p) => WhamageddonFixtures::upcoming($p));

    whamTick();

    expect($upcomings->map(fn ($u) => $u->fresh()->score_adjustment)->all())->toBe([1, 0, 0]);
});

it('hourly tick ignores parties that enabled a different mod', function () {
    Carbon::setTestNow('2026-12-24 12:00:00');
    $party = WhamageddonFixtures::party();
    $upcoming = WhamageddonFixtures::upcoming($party);
    $other = Mod::factory()->create(['code' => 'othermod']);
    $party->mods()->attach($other->id);

    whamTick();

    expect($upcoming->fresh()->score_adjustment)->toBe(0);
});

it('hourly tick ignores a removed whamageddon mod', function () {
    Carbon::setTestNow('2026-12-24 12:00:00');
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    Mod::whereCode('whamageddon')->update(['removed_at' => now()]);

    whamTick();

    expect($upcoming->fresh()->score_adjustment)->toBe(0);
});

it('dispatching the hour tick event runs the listener through the registered binding', function () {
    Carbon::setTestNow('2026-12-24 12:00:00');
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);

    HourTickEvent::dispatch();

    expect($upcoming->fresh()->score_adjustment)->toBe(1);
});

it('isolates a throwing party so the next party still ticks and the failure is logged', function () {
    Carbon::setTestNow('2026-12-24 12:00:00');
    $broken = WhamageddonFixtures::party(['enabled' => true]);
    Party::whereKey($broken->id)->update(['user_id' => User::factory()->create()->id]);
    $healthy = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($healthy);
    Log::spy();

    whamTick();

    expect($upcoming->fresh()->score_adjustment)->toBe(1);
    Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains((string) $message, 'Hourly tick failed'))->once();
});

it('logs the hourly tick through the app logger', function () {
    Carbon::setTestNow('2026-12-24 12:00:00');
    $party = WhamageddonFixtures::party(['enabled' => true]);
    WhamageddonFixtures::upcoming($party);
    Log::spy();

    whamTick();

    Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains((string) $message, 'Whamageddon: Processing hourly tick'))->once();
});

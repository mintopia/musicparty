<?php

use App\Enums\SettingType;
use App\Events\Cron\HourTickEvent;
use App\Events\Mods\PartyModSettingDeletedEvent;
use App\Events\Mods\PartyModSettingUpdatedEvent;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use App\Models\User;
use App\Mods\Whamageddon\Listeners\HourTickListener;
use App\Mods\Whamageddon\Listeners\ModSettingDeletedListener;
use App\Mods\Whamageddon\Listeners\ModSettingUpdatedListener;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

it('registers the three whamageddon listeners', function () {
    $raw = Event::getRawListeners();

    expect(Event::hasListeners(PartyModSettingUpdatedEvent::class))->toBeTrue()
        ->and(Event::hasListeners(PartyModSettingDeletedEvent::class))->toBeTrue()
        ->and(Event::hasListeners(HourTickEvent::class))->toBeTrue()
        ->and($raw[PartyModSettingUpdatedEvent::class])->toContain(ModSettingUpdatedListener::class)
        ->and($raw[PartyModSettingDeletedEvent::class])->toContain(ModSettingDeletedListener::class)
        ->and($raw[HourTickEvent::class])->toContain(HourTickListener::class);
});

it('enables whamageddon when the enabled setting changes to true', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID, 'enabled' => 0]);

    $party->setModSetting('whamageddon', 'enabled', true);

    expect($party->upcoming()->count())->toBe(1);
});

it('pins that creating the enabled row for the first time does not trigger enable because getChanges is empty on insert', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID]);

    $party->setModSetting('whamageddon', 'enabled', true);

    expect($party->upcoming()->count())->toBe(0)->and($this->fake->callsTo('getTrack'))->toBe([]);
});

it('disables whamageddon when the enabled setting changes to a falsy value', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID, 'enabled' => 0]);
    $party->setModSetting('whamageddon', 'enabled', true);
    expect($party->upcoming()->count())->toBe(1);

    $party->setModSetting('whamageddon', 'enabled', 0);

    expect($party->upcoming()->count())->toBe(0);
});

it('pins that setting enabled to boolean false enables rather than disables because b:0; reads as truthy', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID, 'enabled' => 0]);

    $party->setModSetting('whamageddon', 'enabled', false);

    expect($party->upcoming()->count())->toBe(1);
});

it('ignores an unchanged enabled value', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID, 'enabled' => true]);

    $party->setModSetting('whamageddon', 'enabled', true);

    expect($party->upcoming()->count())->toBe(0)->and($this->fake->callsTo('getTrack'))->toBe([]);
});

it('ignores other whamageddon settings', function () {
    $party = WhamageddonFixtures::party();

    $party->setModSetting('whamageddon', 'css_classes', 'other');

    expect($party->upcoming()->count())->toBe(0)->and($this->fake->callsTo('getTrack'))->toBe([]);
});

it('ignores settings from other mods named enabled', function () {
    $party = WhamageddonFixtures::party();
    $other = Mod::factory()->create(['code' => 'othermod']);
    $setting = ModSetting::factory()->create(['mod_id' => $other->id, 'code' => 'enabled', 'type' => SettingType::stBoolean]);
    $row = new PartyModSetting;
    $row->setting()->associate($setting);
    $row->party()->associate($party);
    $row->value = true;
    $row->save();

    expect($party->upcoming()->count())->toBe(0)->and($this->fake->callsTo('getTrack'))->toBe([]);

    $row->delete();

    expect($party->upcoming()->count())->toBe(0);
});

it('disables whamageddon when the enabled setting is deleted', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID, 'enabled' => 0]);
    $party->setModSetting('whamageddon', 'enabled', true);
    expect($party->upcoming()->count())->toBe(1);

    $party->deleteModSetting('whamageddon', 'enabled');

    expect($party->upcoming()->count())->toBe(0)
        ->and($party->fresh()->getModSettingValue('whamageddon', 'upcoming_song_id'))->toBe(0);
});

it('ignores deletion of other whamageddon settings', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID, 'css_classes' => 'x', 'enabled' => 0]);
    $party->setModSetting('whamageddon', 'enabled', true);

    $party->deleteModSetting('whamageddon', 'css_classes');

    expect($party->upcoming()->count())->toBe(1);
});

it('hourly tick processes only active parties with a stored whamageddon enabled row', function () {
    Carbon::setTestNow('2026-12-24 12:00:00');
    $active = WhamageddonFixtures::party(['enabled' => true]);
    $inactive = WhamageddonFixtures::party(['enabled' => true], active: false);
    $falsy = WhamageddonFixtures::party(['enabled' => 0]);
    $noRow = WhamageddonFixtures::party();
    $upcomings = collect([$active, $inactive, $falsy, $noRow])->map(fn (Party $p) => WhamageddonFixtures::upcoming($p));

    whamTick();

    expect($upcomings->map(fn ($u) => $u->fresh()->score_adjustment)->all())->toBe([1, 0, 0, 0]);
});

it('hourly tick ignores enabled rows that belong to other mods', function () {
    Carbon::setTestNow('2026-12-24 12:00:00');
    $party = WhamageddonFixtures::party();
    $upcoming = WhamageddonFixtures::upcoming($party);
    $other = Mod::factory()->create(['code' => 'othermod']);
    $setting = ModSetting::factory()->create(['mod_id' => $other->id, 'code' => 'enabled', 'type' => SettingType::stBoolean]);
    PartyModSetting::withoutEvents(fn () => PartyModSetting::factory()->create(['party_id' => $party->id, 'mod_setting_id' => $setting->id, 'value' => true]));

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

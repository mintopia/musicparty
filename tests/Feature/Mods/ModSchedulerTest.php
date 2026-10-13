<?php

use App\Enums\ScheduleFrequency;
use App\Models\Party;
use App\Mods\Foo\FooMod;
use App\Mods\ModScheduler;
use App\Mods\PartyMods;
use App\Mods\ScheduledAction;
use Illuminate\Support\Facades\Log;
use Tests\Support\FooModFixture;

beforeEach(function () {
    FooModFixture::bindRegistry();
    $this->artisan('mods:sync')->assertSuccessful();
    $this->ran = [];
    $this->record = function (Party $party): void {
        $this->ran[] = $party->id;
    };
    $this->partyA = Party::factory()->create(['active' => true]);
    $this->partyB = Party::factory()->create(['active' => true]);
});

afterEach(function () {
    FooMod::$actions = [];
});

it('runs only the actions matching the frequency', function () {
    FooMod::$actions = [
        new ScheduledAction('hourly', ScheduleFrequency::Hour, $this->record),
        new ScheduledAction('daily', ScheduleFrequency::Day, function () {
            $this->ran[] = 'day';
        }),
    ];
    app(PartyMods::class)->enable($this->partyA, 'foo');

    app(ModScheduler::class)->run(ScheduleFrequency::Hour);
    expect($this->ran)->toBe([$this->partyA->id]);

    $this->ran = [];
    app(ModScheduler::class)->run(ScheduleFrequency::Day);
    expect($this->ran)->toBe(['day']);

    $this->ran = [];
    app(ModScheduler::class)->run(ScheduleFrequency::Minute);
    expect($this->ran)->toBe([]);
});

it('runs only for active parties with the mod enabled', function () {
    FooMod::$actions = [new ScheduledAction('hourly', ScheduleFrequency::Hour, $this->record)];
    $inactive = Party::factory()->create(['active' => false]);
    app(PartyMods::class)->enable($this->partyA, 'foo');
    app(PartyMods::class)->enable($inactive, 'foo');

    app(ModScheduler::class)->run(ScheduleFrequency::Hour);

    expect($this->ran)->toBe([$this->partyA->id]);
});

it('logs a failing party and still runs the others', function () {
    Log::spy();
    FooMod::$actions = [new ScheduledAction('hourly', ScheduleFrequency::Hour, function (Party $party) {
        if ($party->id === $this->partyA->id) {
            throw new RuntimeException('boom');
        }
        $this->ran[] = $party->id;
    })];
    app(PartyMods::class)->enable($this->partyA, 'foo');
    app(PartyMods::class)->enable($this->partyB, 'foo');

    app(ModScheduler::class)->run(ScheduleFrequency::Hour);

    expect($this->ran)->toBe([$this->partyB->id]);
    Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains((string) $message, 'hourly failed'))->once();
});

<?php

use App\Models\Party;
use App\Models\UpcomingSong;
use App\Mods\Whamageddon\Whamageddon;
use App\Services\ModSettingsStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\Support\WhamageddonFixtures;

beforeEach(function () {
    $this->fake = WhamageddonFixtures::fake();
    WhamageddonFixtures::install();
});

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function whamAt(string $time): void
{
    test()->travelTo(CarbonImmutable::parse($time, 'UTC'));
}

it('does nothing for an inactive party', function () {
    $party = WhamageddonFixtures::party(['enabled' => true], active: false);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 12:00:00');

    new Whamageddon($party)->hourly();

    expect($upcoming->fresh()->score_adjustment)->toBe(0)
        ->and($party->upcoming()->count())->toBe(1);
});

it('does nothing when the mod is not enabled for the party', function () {
    $party = WhamageddonFixtures::party();
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 12:00:00');

    new Whamageddon($party)->hourly();

    expect($upcoming->fresh()->score_adjustment)->toBe(0);
});

it('applies the hourly upvote only outside the 2AM to 9AM window', function (string $time, bool $skipped) {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt($time);

    new Whamageddon($party)->hourly();

    expect($upcoming->fresh()->score_adjustment)->toBe($skipped ? 0 : 1);
})->with([
    'hour 0' => ['2026-12-24 00:00:00', false],
    'hour 1' => ['2026-12-24 01:59:59', false],
    'hour 2' => ['2026-12-24 02:00:00', true],
    'hour 5' => ['2026-12-24 05:30:00', true],
    'hour 9' => ['2026-12-24 09:59:59', true],
    'hour 10' => ['2026-12-24 10:00:00', false],
    'hour 15' => ['2026-12-24 15:00:00', false],
    'hour 22' => ['2026-12-24 22:00:00', false],
]);

it('increments the score by score_adjustment and recalculates the score', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 10:00:00');

    $whamageddon = new Whamageddon($party);
    $whamageddon->hourly();
    $whamageddon->hourly();

    $upcoming->refresh();
    expect($upcoming->score_adjustment)->toBe(2)->and($upcoming->score)->toBe(2);
});

it('bumps the score by the configured score_per_hour value', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'score_per_hour' => 5]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 12:00:00');

    new Whamageddon($party)->hourly();

    $upcoming->refresh();
    expect($upcoming->score_adjustment)->toBe(5)->and($upcoming->score)->toBe(5);
});

it('adds the song to the party when there is no upcoming song', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'track' => WhamageddonFixtures::TRACK_ID]);
    whamAt('2026-12-24 12:00:00');

    new Whamageddon($party)->hourly();

    $upcoming = $party->upcoming()->sole();
    expect($upcoming->song->provider_track_id)->toBe(WhamageddonFixtures::TRACK_ID)
        ->and($upcoming->score_adjustment)->toBe(0)
        ->and(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'upcoming_song_id'))->toBe($upcoming->id);
});

it('adds nothing in the quiet window even when there is no upcoming song', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    whamAt('2026-12-24 04:00:00');

    new Whamageddon($party)->hourly();

    expect($party->upcoming()->count())->toBe(0);
});

it('treats a stale upcoming_song_id as no upcoming song and adds a new one', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'upcoming_song_id' => 99999]);
    whamAt('2026-12-24 12:00:00');

    new Whamageddon($party)->hourly();

    expect($party->upcoming()->count())->toBe(1);
});

it('holds playback until 10AM the next day from 23:00', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 23:00:00');

    new Whamageddon($party)->hourly();

    $upcoming->refresh();
    expect($upcoming->score_adjustment)->toBe(1)
        ->and(CarbonImmutable::parse($upcoming->getRawOriginal('not_before'), 'UTC')->toDateTimeString())->toBe('2026-12-25 10:00:00');
});

it('does not hold playback at 22:59', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 22:59:59');

    new Whamageddon($party)->hourly();

    expect($upcoming->fresh()->getRawOriginal('not_before'))->toBeNull();
});

it('keeps the hold through hours 0 and 1 and in the quiet window', function (string $time) {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party, ['not_before' => '2026-12-25 10:00:00']);
    whamAt($time);

    new Whamageddon($party)->hourly();

    expect($upcoming->fresh()->getRawOriginal('not_before'))->not->toBeNull();
})->with(['2026-12-25 00:00:00', '2026-12-25 01:00:00', '2026-12-25 05:00:00', '2026-12-25 09:00:00']);

it('clears the hold from 10:00 onwards', function (string $time) {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party, ['not_before' => '2026-12-25 10:00:00']);
    whamAt($time);

    new Whamageddon($party)->hourly();

    $upcoming->refresh();
    expect($upcoming->getRawOriginal('not_before'))->toBeNull()->and($upcoming->score_adjustment)->toBe(1);
})->with(['2026-12-25 10:00:00', '2026-12-25 18:00:00', '2026-12-25 22:00:00']);

it('casts not_before to a Carbon instance', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party, ['not_before' => '2026-12-25 10:00:00']);

    $fresh = UpcomingSong::find($upcoming->id);

    expect($fresh->not_before)->toBeInstanceOf(Carbon::class)
        ->and($fresh->hasCast('not_before'))->toBeTrue()
        ->and($fresh->hasCast('queued_at'))->toBeTrue();
});

it('waits without re-adding until two hours after the upcoming song was queued', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'track' => WhamageddonFixtures::TRACK_ID]);
    $upcoming = WhamageddonFixtures::upcoming($party, ['queued_at' => '2026-12-24 12:00:00']);
    whamAt('2026-12-24 13:59:59');

    new Whamageddon($party)->hourly();

    expect($party->upcoming()->count())->toBe(1)
        ->and($upcoming->fresh()->score_adjustment)->toBe(0)
        ->and(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'upcoming_song_id'))->toBe($upcoming->id)
        ->and($this->fake->callsTo('getTrack'))->toBe([]);
});

it('re-adds a new upcoming row two hours after the previous one was queued', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'track' => WhamageddonFixtures::TRACK_ID]);
    $old = WhamageddonFixtures::upcoming($party, ['queued_at' => '2026-12-24 12:00:00']);
    whamAt('2026-12-24 14:00:00');

    new Whamageddon($party)->hourly();

    expect($party->upcoming()->count())->toBe(2);
    $new = $party->upcoming()->whereKeyNot($old->id)->sole();
    expect($new->queued_at)->toBeNull()
        ->and($new->score_adjustment)->toBe(0)
        ->and($new->user_id)->toBeNull()
        ->and($old->fresh()->score_adjustment)->toBe(0)
        ->and(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'upcoming_song_id'))->toBe($new->id);
});

it('still runs delayPlayback against the old queued upcoming after re-adding', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'track' => WhamageddonFixtures::TRACK_ID]);
    $old = WhamageddonFixtures::upcoming($party, ['queued_at' => '2026-12-24 21:00:00']);
    whamAt('2026-12-24 23:00:00');

    new Whamageddon($party)->hourly();

    $new = $party->upcoming()->whereKeyNot($old->id)->sole();
    expect($old->fresh()->getRawOriginal('not_before'))->not->toBeNull()
        ->and($new->not_before)->toBeNull();
});

it('applies the quiet window in the party timezone', function (string $utc, int $expected) {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    Party::whereKey($party->id)->update(['timezone' => 'America/New_York']);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt($utc);

    new Whamageddon($party->fresh())->hourly();

    expect(config('app.timezone'))->toBe('UTC')
        ->and($upcoming->fresh()->score_adjustment)->toBe($expected);
})->with([
    '09:30 UTC is 04:30 in New York: quiet' => ['2026-12-24 09:30:00', 0],
    '15:30 UTC is 10:30 in New York: active' => ['2026-12-24 15:30:00', 1],
    '03:00 UTC is 22:00 in New York: active' => ['2026-12-25 03:00:00', 1],
]);

it('holds playback until 10AM party time the next day, stored as UTC', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    Party::whereKey($party->id)->update(['timezone' => 'America/New_York']);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-25 04:00:00');

    new Whamageddon($party->fresh())->hourly();

    expect($upcoming->fresh()->getRawOriginal('not_before'))->toBe('2026-12-25 15:00:00');
});

it('does not hold playback at 23:00 UTC when it is only 18:00 party time', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    Party::whereKey($party->id)->update(['timezone' => 'America/New_York']);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 23:00:00');

    new Whamageddon($party->fresh())->hourly();

    expect($upcoming->fresh()->getRawOriginal('not_before'))->toBeNull();
});

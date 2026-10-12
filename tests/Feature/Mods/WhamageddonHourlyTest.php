<?php

use App\Models\UpcomingSong;
use App\Mods\Whamageddon\Whamageddon;
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

    (new Whamageddon($party))->hourly();

    expect($upcoming->fresh()->score_adjustment)->toBe(0)
        ->and($party->upcoming()->count())->toBe(1);
});

it('does nothing when the enabled setting is falsy', function (mixed $value) {
    $party = WhamageddonFixtures::party(['enabled' => $value]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 12:00:00');

    (new Whamageddon($party))->hourly();

    expect($upcoming->fresh()->score_adjustment)->toBe(0);
})->with([0, null]);

it('pins that a stored boolean false reads back as the truthy string b:0; so hourly cannot be switched off with false', function () {
    $party = WhamageddonFixtures::party(['enabled' => false]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 12:00:00');

    (new Whamageddon($party))->hourly();

    expect($party->fresh()->getModSettingValue('whamageddon', 'enabled'))->toBe('b:0;')
        ->and($upcoming->fresh()->score_adjustment)->toBe(1);
});

it('treats a party with no stored enabled value as enabled because the false default reads as true', function () {
    $party = WhamageddonFixtures::party();
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 12:00:00');

    (new Whamageddon($party))->hourly();

    expect($upcoming->fresh()->score_adjustment)->toBe(1);
});

it('applies the hourly upvote only outside the 2AM to 9AM window', function (string $time, bool $skipped) {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt($time);

    (new Whamageddon($party))->hourly();

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

it('ignores the votes setting because the code reads score_adjustment', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'votes' => 5]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 12:00:00');

    (new Whamageddon($party))->hourly();

    expect($upcoming->fresh()->score_adjustment)->toBe(1);
});

it('adds the song to the party when there is no upcoming song', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'spotify_id' => WhamageddonFixtures::TRACK_ID]);
    whamAt('2026-12-24 12:00:00');

    (new Whamageddon($party))->hourly();

    $upcoming = $party->upcoming()->sole();
    expect($upcoming->song->spotify_id)->toBe(WhamageddonFixtures::TRACK_ID)
        ->and($upcoming->score_adjustment)->toBe(0)
        ->and($party->fresh()->getModSettingValue('whamageddon', 'upcoming_song_id'))->toBe($upcoming->id);
});

it('adds nothing in the quiet window even when there is no upcoming song', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    whamAt('2026-12-24 04:00:00');

    (new Whamageddon($party))->hourly();

    expect($party->upcoming()->count())->toBe(0);
});

it('treats a stale upcoming_song_id as no upcoming song and adds a new one', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'upcoming_song_id' => 99999]);
    whamAt('2026-12-24 12:00:00');

    (new Whamageddon($party))->hourly();

    expect($party->upcoming()->count())->toBe(1);
});

it('holds playback until 10AM the next day from 23:00', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 23:00:00');

    (new Whamageddon($party))->hourly();

    $upcoming->refresh();
    expect($upcoming->score_adjustment)->toBe(1)
        ->and(CarbonImmutable::parse($upcoming->getRawOriginal('not_before'), 'UTC')->toDateTimeString())->toBe('2026-12-25 10:00:00');
});

it('does not hold playback at 22:59', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 22:59:59');

    (new Whamageddon($party))->hourly();

    expect($upcoming->fresh()->getRawOriginal('not_before'))->toBeNull();
});

it('keeps the hold through hours 0 and 1 and in the quiet window', function (string $time) {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party, ['not_before' => '2026-12-25 10:00:00']);
    whamAt($time);

    (new Whamageddon($party))->hourly();

    expect($upcoming->fresh()->getRawOriginal('not_before'))->not->toBeNull();
})->with(['2026-12-25 00:00:00', '2026-12-25 01:00:00', '2026-12-25 05:00:00', '2026-12-25 09:00:00']);

it('clears the hold from 10:00 onwards', function (string $time) {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party, ['not_before' => '2026-12-25 10:00:00']);
    whamAt($time);

    (new Whamageddon($party))->hourly();

    $upcoming->refresh();
    expect($upcoming->getRawOriginal('not_before'))->toBeNull()->and($upcoming->score_adjustment)->toBe(1);
})->with(['2026-12-25 10:00:00', '2026-12-25 18:00:00', '2026-12-25 22:00:00']);

it('pins the not_before cast typo so it is a plain string and not a Carbon instance until E5 fixes the cast', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party, ['not_before' => '2026-12-25 10:00:00']);

    $fresh = UpcomingSong::find($upcoming->id);

    expect($fresh->not_before)->toBeString()
        ->and($fresh->not_before)->not->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->hasCast('not_before'))->toBeFalse()
        ->and($fresh->hasCast('queued_at'))->toBeTrue();
});

it('waits without re-adding until two hours after the upcoming song was queued', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'spotify_id' => WhamageddonFixtures::TRACK_ID]);
    $upcoming = WhamageddonFixtures::upcoming($party, ['queued_at' => '2026-12-24 12:00:00']);
    whamAt('2026-12-24 13:59:59');

    (new Whamageddon($party))->hourly();

    expect($party->upcoming()->count())->toBe(1)
        ->and($upcoming->fresh()->score_adjustment)->toBe(0)
        ->and($party->fresh()->getModSettingValue('whamageddon', 'upcoming_song_id'))->toBe($upcoming->id)
        ->and($this->fake->callsTo('getTrack'))->toBe([]);
});

it('re-adds a new upcoming row two hours after the previous one was queued', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'spotify_id' => WhamageddonFixtures::TRACK_ID]);
    $old = WhamageddonFixtures::upcoming($party, ['queued_at' => '2026-12-24 12:00:00']);
    whamAt('2026-12-24 14:00:00');

    (new Whamageddon($party))->hourly();

    expect($party->upcoming()->count())->toBe(2);
    $new = $party->upcoming()->whereKeyNot($old->id)->sole();
    expect($new->queued_at)->toBeNull()
        ->and($new->score_adjustment)->toBe(0)
        ->and($new->user_id)->toBeNull()
        ->and($old->fresh()->score_adjustment)->toBe(0)
        ->and($party->fresh()->getModSettingValue('whamageddon', 'upcoming_song_id'))->toBe($new->id);
});

it('still runs delayPlayback against the old queued upcoming after re-adding', function () {
    $party = WhamageddonFixtures::party(['enabled' => true, 'spotify_id' => WhamageddonFixtures::TRACK_ID]);
    $old = WhamageddonFixtures::upcoming($party, ['queued_at' => '2026-12-24 21:00:00']);
    whamAt('2026-12-24 23:00:00');

    (new Whamageddon($party))->hourly();

    $new = $party->upcoming()->whereKeyNot($old->id)->sole();
    expect($old->fresh()->getRawOriginal('not_before'))->not->toBeNull()
        ->and($new->not_before)->toBeNull();
});

it('applies the hour of day in the app timezone only', function () {
    $party = WhamageddonFixtures::party(['enabled' => true]);
    $upcoming = WhamageddonFixtures::upcoming($party);
    whamAt('2026-12-24 09:30:00');

    (new Whamageddon($party))->hourly();

    expect(config('app.timezone'))->toBe('UTC')
        ->and($upcoming->fresh()->score_adjustment)->toBe(0);
});

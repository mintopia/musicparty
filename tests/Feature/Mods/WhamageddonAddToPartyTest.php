<?php

use App\Models\PartyModSetting;
use App\Models\UpcomingSong;
use App\Mods\Whamageddon\Whamageddon;
use App\Services\ModSettingsStore;
use Tests\Support\WhamageddonFixtures;

beforeEach(function () {
    $this->fake = WhamageddonFixtures::fake();
    WhamageddonFixtures::install();
});

it('adds the configured track to the party on enable', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID]);

    (new Whamageddon($party))->enable();

    $upcoming = $party->upcoming()->sole();
    expect($upcoming->song->spotify_id)->toBe(WhamageddonFixtures::TRACK_ID)
        ->and($upcoming->user_id)->toBeNull()
        ->and($upcoming->fallback_override)->toBe('Whamageddon')
        ->and($upcoming->css_classes)->toBe('whamageddon')
        ->and(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'upcoming_song_id'))->toBe($upcoming->id)
        ->and($this->fake->callsTo('getTrack'))->toBe([[WhamageddonFixtures::TRACK_ID]]);
});

it('pins that a newly created upcoming keeps score_adjustment 0 because the guard compares a null attribute to 0', function () {
    $party = WhamageddonFixtures::party();

    (new Whamageddon($party))->enable();

    expect($party->upcoming()->sole()->score_adjustment)->toBe(0);
});

it('uses the overridden fallback name and css classes', function () {
    $party = WhamageddonFixtures::party(['fallback_name' => 'Wham', 'css_classes' => 'a b']);

    (new Whamageddon($party))->enable();

    $upcoming = $party->upcoming()->sole();
    expect($upcoming->fallback_override)->toBe('Wham')->and($upcoming->css_classes)->toBe('a b');
});

it('adds nothing when the spotify id is null', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => null]);

    (new Whamageddon($party))->enable();

    expect($party->upcoming()->count())->toBe(0)
        ->and($this->fake->callsTo('getTrack'))->toBe([]);
});

it('adds nothing when spotify returns no track', function () {
    $this->fake->respond('getTrack', []);
    $party = WhamageddonFixtures::party();

    (new Whamageddon($party))->enable();

    expect($party->upcoming()->count())->toBe(0)
        ->and($this->fake->callsTo('getTrack'))->toHaveCount(1);
});

it('does nothing on enable when an upcoming song is already tracked', function () {
    $party = WhamageddonFixtures::party();
    $existing = WhamageddonFixtures::upcoming($party);

    (new Whamageddon($party))->enable();

    expect($party->upcoming()->count())->toBe(1)
        ->and($existing->fresh()->score_adjustment)->toBe(0)
        ->and($this->fake->callsTo('getTrack'))->toBe([]);
});

it('reuses an existing unqueued upcoming for the same track', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID]);
    $existing = WhamageddonFixtures::upcoming($party, ['user_id' => null], track: false);

    (new Whamageddon($party))->enable();

    $upcoming = $party->upcoming()->sole();
    expect($upcoming->id)->toBe($existing->id)
        ->and($upcoming->score_adjustment)->toBe(1)
        ->and($upcoming->css_classes)->toBe('whamageddon')
        ->and(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'upcoming_song_id'))->toBe($existing->id)
        ->and($this->fake->callsTo('getTrack'))->toBe([]);
});

it('does not reuse an existing queued upcoming and creates a new row', function () {
    $party = WhamageddonFixtures::party(['spotify_id' => WhamageddonFixtures::TRACK_ID]);
    $queued = WhamageddonFixtures::upcoming($party, ['queued_at' => now()], track: false);

    (new Whamageddon($party))->enable();

    expect($party->upcoming()->count())->toBe(2);
    $new = $party->upcoming()->whereKeyNot($queued->id)->sole();
    expect(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'upcoming_song_id'))->toBe($new->id);
});

it('deletes the upcoming song and the upcoming_song_id setting on disable', function () {
    $party = WhamageddonFixtures::party();
    (new Whamageddon($party))->enable();
    $id = $party->upcoming()->sole()->id;

    (new Whamageddon($party))->disable();

    expect(UpcomingSong::find($id))->toBeNull()
        ->and(PartyModSetting::whereParty_id($party->id)->whereHas('setting', fn ($q) => $q->where('code', 'upcoming_song_id'))->exists())->toBeFalse()
        ->and(app(ModSettingsStore::class)->value($party->fresh(), 'whamageddon', 'upcoming_song_id'))->toBe(0);
});

it('disable is safe when nothing was added', function () {
    $party = WhamageddonFixtures::party();

    (new Whamageddon($party))->disable();

    expect($party->upcoming()->count())->toBe(0);
});

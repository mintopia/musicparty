<?php

use App\Models\Party;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;

it('never attributes a play to a Request from another Party', function () {
    $song = Song::factory()->create();
    $partyA = Party::factory()->create();
    $partyB = Party::factory()->create();
    UpcomingSong::factory()->for($partyB)->for($song)->sentToPlayer()->create();
    $played = PlayedSong::factory()->for($partyA)->for($song)->create();

    expect($played->findRequestedSong())->toBeNull();
});

it('links repeated plays of a track to Requests in the order they were sent', function () {
    $song = Song::factory()->create();
    $party = Party::factory()->create();
    $first = UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create(['queued_at' => now()->subMinutes(10)]);
    $second = UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create(['queued_at' => now()->subMinutes(5)]);
    UpcomingSong::factory()->for($party)->for($song)->create();

    foreach ([$first, $second] as $expected) {
        $played = PlayedSong::factory()->for($party)->for($song)->create();
        $played->findRequestedSong();
        $played->save();

        expect($played->upcoming_song_id)->toBe($expected->id);
    }
});

it('links a relinked play through the id that was sent to the player', function () {
    $song = Song::factory()->create(['provider_track_id' => 'ORIGINAL']);
    $party = Party::factory()->create();
    $request = UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create();
    $played = PlayedSong::factory()->for($party)->for(Song::factory()->create(['provider_track_id' => 'RELINKED']))
        ->create(['relinked_from' => 'ORIGINAL']);

    expect($played->findRequestedSong()?->id)->toBe($request->id);
});

it('falls back to song matching only for a Request with no recorded sent id', function () {
    $song = Song::factory()->create();
    $party = Party::factory()->create();
    $legacy = UpcomingSong::factory()->for($party)->for($song)->create(['queued_at' => now()->subMinute()]);
    $played = PlayedSong::factory()->for($party)->for($song)->create();

    expect($played->findRequestedSong()?->id)->toBe($legacy->id);
});

it('leaves a track played outside Music Party unlinked', function () {
    $party = Party::factory()->create();
    UpcomingSong::factory()->for($party)->sentToPlayer()->create();
    $played = PlayedSong::factory()->for($party)->for(Song::factory()->create())->create();

    expect($played->findRequestedSong())->toBeNull()
        ->and($played->upcoming_song_id)->toBeNull();
});

it('keeps an existing link without searching', function () {
    $party = Party::factory()->create();
    $request = UpcomingSong::factory()->for($party)->sentToPlayer()->create();
    $played = PlayedSong::factory()->for($party)->for($request->song)->create(['upcoming_song_id' => $request->id]);

    expect($played->findRequestedSong()?->id)->toBe($request->id);
});

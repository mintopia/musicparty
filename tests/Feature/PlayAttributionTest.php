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

it('prefers the most recently sent unplayed Request for the track', function () {
    $song = Song::factory()->create();
    $party = Party::factory()->create();
    $mk = fn (array $state) => UpcomingSong::factory()->for($party)->for($song)->create($state);
    $mk(['queued_at' => now()->subMinutes(30)]);
    $latest = $mk(['queued_at' => now()->subMinutes(5)]);
    $mk(['queued_at' => null]);
    $alreadyPlayed = $mk(['queued_at' => now()->subMinute()]);
    PlayedSong::factory()->for($party)->for($song)->create(['upcoming_song_id' => $alreadyPlayed->id]);
    $played = PlayedSong::factory()->for($party)->for($song)->create();

    expect($played->findRequestedSong()?->id)->toBe($latest->id);
});

it('keeps an existing link without searching', function () {
    $party = Party::factory()->create();
    $request = UpcomingSong::factory()->for($party)->sentToPlayer()->create();
    $played = PlayedSong::factory()->for($party)->for($request->song)->create(['upcoming_song_id' => $request->id]);

    expect($played->findRequestedSong()?->id)->toBe($request->id);
});

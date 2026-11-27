<?php

use App\Models\Artist;
use App\Models\Party;
use App\Models\PartySearch;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Search\PartySearchSuggestions;

beforeEach(function () {
    $this->party = Party::factory()->create();
    $this->suggestions = app(PartySearchSuggestions::class);
});

it('records searches per party and trims them', function () {
    $this->suggestions->record($this->party, User::factory()->create(), '  daft punk ');
    $this->suggestions->record($this->party, null, '   ');

    expect(PartySearch::query()->pluck('query')->all())->toBe(['daft punk']);
});

it('lists recent searches newest first without repeats across guests', function () {
    $this->suggestions->record($this->party, null, 'Levels');
    $this->suggestions->record($this->party, null, 'sandstorm');
    $this->suggestions->record($this->party, null, 'levels');
    PartySearch::factory()->create(['query' => 'other party']);

    expect($this->suggestions->recent($this->party, 5))->toBe(['levels', 'sandstorm']);
});

it('limits recent searches', function () {
    foreach (['a', 'b', 'c'] as $query) {
        $this->suggestions->record($this->party, null, $query);
    }

    expect($this->suggestions->recent($this->party, 2))->toBe(['c', 'b']);
});

it('ranks artists by request count at this party tonight', function () {
    $daft = Artist::factory()->create(['name' => 'Daft Punk']);
    $abba = Artist::factory()->create(['name' => 'ABBA']);
    $request = function (Party $party, Artist $artist, array $attributes = []) {
        $song = Song::factory()->create();
        $song->artists()->sync([$artist->id]);

        return UpcomingSong::factory()->create(['party_id' => $party->id, 'song_id' => $song->id, ...$attributes]);
    };
    $request($this->party, $daft);
    $request($this->party, $daft);
    $request($this->party, $abba);
    $request($this->party, $abba, ['created_at' => now()->subDays(2)]);
    $request(Party::factory()->create(), $abba);
    $request(Party::factory()->create(), $abba);

    $top = $this->suggestions->topArtists($this->party, 5);

    expect($top)->toBe([
        ['id' => $daft->provider_artist_id, 'name' => 'Daft Punk', 'image_url' => null, 'requests' => 2],
        ['id' => $abba->provider_artist_id, 'name' => 'ABBA', 'image_url' => null, 'requests' => 1],
    ]);
});

it('seeds a more-like search from the playing track', function () {
    expect($this->suggestions->nowPlaying($this->party))->toBeNull();

    $song = Song::factory()->create(['name' => 'Midnight City']);
    $song->artists()->sync([Artist::factory()->create(['name' => 'M83'])->id]);
    $this->party->forceFill(['song_id' => $song->id])->save();

    expect($this->suggestions->nowPlaying($this->party->fresh()))->toMatchArray([
        'name' => 'Midnight City',
        'artist' => 'M83',
        'query' => 'M83',
        'image_url' => $song->album->image_url,
    ]);
});

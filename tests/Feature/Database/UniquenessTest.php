<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeSpotify;

function loadMigration(string $name): object
{
    return require database_path("migrations/{$name}.php");
}

it('rejects a second vote by the same user on the same request', function () {
    $upcoming = UpcomingSong::factory()->create();
    $user = User::factory()->create();
    Vote::factory()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => $user->id]);

    Vote::factory()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => $user->id]);
})->throws(UniqueConstraintViolationException::class);

it('yields one vote row when a concurrent insert wins the race', function () {
    $upcoming = UpcomingSong::factory()->for(Party::factory()->create(['downvotes' => true]))->create();
    $user = User::factory()->create();
    PartyMember::factory()->for($upcoming->party)->for($user)->create();

    Vote::creating(function (Vote $vote) {
        Vote::withoutEvents(fn () => DB::table('votes')->insert([
            'upcoming_song_id' => $vote->upcoming_song_id,
            'user_id' => $vote->user_id,
            'value' => 1,
        ]));
    });

    $vote = $upcoming->downvote($user);

    expect(Vote::count())->toBe(1)
        ->and($vote->value)->toBe(-1)
        ->and(Vote::first()->value)->toBe(-1);
    Vote::flushEventListeners();
});

it('returns the existing catalogue rows when the insert loses a race', function () {
    $track = FakeSpotify::track('raced', 'Raced');
    $created = Song::fromSpotify($track);

    Song::creating(fn () => throw new UniqueConstraintViolationException('sqlite', 'insert', [], new Exception));
    DB::table('songs')->where('id', $created->id)->update(['name' => 'kept']);

    expect(Song::fromSpotify($track)->id)->toBe($created->id)
        ->and(Song::count())->toBe(1)
        ->and(Album::count())->toBe(1)
        ->and(Artist::count())->toBe(count($track->artists));
    Song::flushEventListeners();
});

it('enforces unique spotify ids on catalogue tables', function (string $model) {
    $model::factory()->create(['spotify_id' => 'dup']);

    $model::factory()->create(['spotify_id' => 'dup']);
})->with([Song::class, Album::class, Artist::class])->throws(UniqueConstraintViolationException::class);

it('de-duplicates votes keeping the latest and recomputes the score', function () {
    $migration = loadMigration('2026_10_13_120000_add_unique_index_to_votes');
    $migration->down();
    $upcoming = UpcomingSong::factory()->create();
    $user = User::factory()->create();
    foreach ([1, -1] as $value) {
        DB::table('votes')->insert(['upcoming_song_id' => $upcoming->id, 'user_id' => $user->id, 'value' => $value]);
    }
    DB::table('upcoming_songs')->where('id', $upcoming->id)->update(['score' => 99]);

    $migration->up();

    expect(DB::table('votes')->count())->toBe(1)
        ->and(DB::table('votes')->value('value'))->toBe(-1)
        ->and((int) DB::table('upcoming_songs')->where('id', $upcoming->id)->value('score'))->toBe(-1);
});

it('de-duplicates catalogue rows and re-points every foreign key', function () {
    $migration = loadMigration('2026_10_13_120100_add_unique_spotify_id_to_catalogue');
    $migration->down();

    $albums = Album::factory()->count(2)->create(['spotify_id' => 'same', 'provider_id' => null]);
    $artists = Artist::factory()->count(2)->create(['spotify_id' => 'same', 'provider_id' => null]);
    $songs = collect([0, 1])->map(fn ($i) => Song::factory()->create([
        'spotify_id' => 'same',
        'provider_id' => null,
        'album_id' => $albums[$i]->id,
    ]));
    foreach ($songs as $i => $song) {
        $song->artists()->attach($artists[$i]);
        $song->artists()->attach($artists[0]);
    }
    $party = Party::factory()->create();
    DB::table('parties')->where('id', $party->id)->update(['song_id' => $songs[1]->id]);
    $upcoming = UpcomingSong::factory()->create(['song_id' => $songs[1]->id]);
    $played = PlayedSong::factory()->create(['song_id' => $songs[1]->id]);

    $migration->up();

    $survivor = $songs[0]->id;
    expect(Song::where('spotify_id', 'same')->count())->toBe(1)
        ->and(Album::where('spotify_id', 'same')->count())->toBe(1)
        ->and(Artist::where('spotify_id', 'same')->count())->toBe(1)
        ->and(Song::find($survivor)->album_id)->toBe($albums[0]->id)
        ->and($party->fresh()->song_id)->toBe($survivor)
        ->and($upcoming->fresh()->song_id)->toBe($survivor)
        ->and($played->fresh()->song_id)->toBe($survivor)
        ->and(DB::table('artist_song')->where('song_id', $survivor)->where('artist_id', $artists[0]->id)->count())->toBe(1)
        ->and(DB::table('artist_song')->where('artist_id', $artists[1]->id)->count())->toBe(0);
});

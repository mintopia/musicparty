<?php

use App\Enums\PartyModerationType;
use App\Models\Party;
use App\Models\PartyModeration;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const PROVIDER_IDENTITY_MIGRATION = '2026_10_13_140000_add_provider_identity_columns';

function providerIdentityMigration(): object
{
    return require database_path('migrations/'.PROVIDER_IDENTITY_MIGRATION.'.php');
}

function seedLegacyCatalogue(int $count): void
{
    foreach (range(1, $count) as $i) {
        DB::table('artists')->insert(['name' => "Artist {$i}", 'spotify_id' => "artist{$i}"]);
        DB::table('albums')->insert(['name' => "Album {$i}", 'spotify_id' => "album{$i}", 'image_url' => '']);
        DB::table('songs')->insert([
            'name' => "Song {$i}",
            'spotify_id' => "track{$i}",
            'length' => 1000,
            'album_id' => $i,
        ]);
        DB::table('artist_song')->insert(['artist_id' => $i, 'song_id' => $i, 'created_at' => now(), 'updated_at' => now()]);
    }
}

it('backfills provider identity from spotify_id across chunk boundaries and enforces the pair', function () {
    $migration = providerIdentityMigration();
    $migration->down();
    expect(Schema::hasColumn('songs', 'provider_track_id'))->toBeFalse()
        ->and(Schema::hasColumn('parties', 'provider_id'))->toBeFalse();

    seedLegacyCatalogue(5);
    $party = Party::factory()->create();

    $migration->chunkSize = 2;
    $migration->up();
    $migration->up();

    foreach (['songs' => ['track', 'provider_track_id'], 'albums' => ['album', 'provider_album_id'], 'artists' => ['artist', 'provider_artist_id']] as $table => [$prefix, $column]) {
        expect(DB::table($table)->where('provider_id', 'spotify')->whereColumn($column, 'spotify_id')->count())->toBe(5)
            ->and(DB::table($table)->where($column, "{$prefix}3")->value('spotify_id'))->toBe("{$prefix}3");
    }
    expect($party->fresh()->provider_id)->toBe('spotify');

    expect(fn () => DB::table('songs')->insert(['name' => 'Dup', 'spotify_id' => 'other', 'provider_id' => 'spotify', 'provider_track_id' => 'track1', 'length' => 1, 'album_id' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
    DB::table('songs')->insert(['name' => 'Other provider', 'spotify_id' => 'other', 'provider_id' => 'fake', 'provider_track_id' => 'track1', 'length' => 1, 'album_id' => 1]);

    $migration->down();
    expect(Schema::hasColumn('albums', 'provider_album_id'))->toBeFalse()
        ->and(Schema::hasColumn('albums', 'spotify_id'))->toBeTrue();
    $migration->up();
});

it('keeps requests, played songs, votes and moderation rules resolving against backfilled rows', function () {
    $migration = providerIdentityMigration();
    $migration->down();
    seedLegacyCatalogue(2);
    $party = Party::factory()->create();
    $user = User::factory()->create();
    $upcomingId = DB::table('upcoming_songs')->insertGetId([
        'party_id' => $party->id, 'song_id' => 1, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $migration->up();

    $upcoming = UpcomingSong::findOrFail($upcomingId);
    $vote = Vote::factory()->create(['upcoming_song_id' => $upcoming->id, 'user_id' => $user->id]);
    DB::table('upcoming_songs')->where('id', $upcoming->id)->update(['queued_at' => now()]);
    $played = PlayedSong::factory()->create(['party_id' => $party->id, 'song_id' => 1, 'upcoming_song_id' => null]);

    $song = Song::with(['album', 'artists'])->findOrFail(1);
    $track = $song->toTrack();
    expect($track->id)->toBe('track1')
        ->and($track->album->id)->toBe('album1')
        ->and($track->artists[0]->id)->toBe('artist1')
        ->and($vote->upcomingSong->song->provider_track_id)->toBe('track1')
        ->and($played->findRequestedSong()?->id)->toBe($upcoming->id)
        ->and(Song::where(['provider_id' => 'spotify', 'provider_track_id' => 'track2'])->exists())->toBeTrue();

    foreach ([[PartyModerationType::mtId, 'track1'], [PartyModerationType::mtArtistId, 'artist1'], [PartyModerationType::mtAlbumId, 'album1']] as [$type, $value]) {
        $rule = (new PartyModeration)->forceFill(['type' => $type, 'value' => $value, 'regex' => false]);
        expect($rule->match($track))->toBeTrue();
        $rule->value = 'nomatch';
        expect($rule->match($track))->toBeFalse();
    }
});

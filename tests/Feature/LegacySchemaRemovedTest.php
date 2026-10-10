<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has no legacy tables', function (string $table) {
    expect(Schema::hasTable($table))->toBeFalse();
})->with([
    'song_ratings', 'votes', 'artist_song', 'played_songs', 'upcoming_songs', 'songs', 'albums', 'artists',
    'party_moderations', 'themes', 'websockets_statistics_entries', 'play_ratings', 'party_logs',
    'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring',
]);

it('has no legacy columns', function (string $table, string $column) {
    expect(Schema::hasColumn($table, $column))->toBeFalse();
})->with(function () {
    $parties = [
        'song_id', 'song_started_at', 'recent_device_id', 'device_id', 'device_name', 'queue', 'force', 'poll',
        'last_updated_at', 'weighted', 'trustscore', 'trusted_user_id', 'show_qrcode', 'active',
        'backup_playlist_id', 'backup_playlist_name',
    ];

    foreach ($parties as $column) {
        yield "parties.{$column}" => ['parties', $column];
    }

    yield 'party_members.trustscore' => ['party_members', 'trustscore'];

    foreach (['status', 'status_updated_at', 'market'] as $column) {
        yield "users.{$column}" => ['users', $column];
    }
});

it('keeps the columns v3 still reads', function (string $table, string $column) {
    expect(Schema::hasColumn($table, $column))->toBeTrue();
})->with([
    ['parties', 'downvotes'],
    ['parties', 'downvotes_per_hour'],
    ['parties', 'history_playlist_id'],
    ['parties', 'state'],
    ['party_log_entries', 'party_id'],
]);

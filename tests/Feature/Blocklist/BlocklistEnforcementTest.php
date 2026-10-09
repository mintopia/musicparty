<?php

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Queue\BlocklistMatchType;
use App\Events\Party\RequestRejectedEvent;
use App\Models\BlocklistEntry;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function blockableTrack(string $id, string $isrc = 'ISRC-A'): TrackData
{
    return new TrackData('fake', $id, "Midnight Song {$id}", [new ArtistData('artist-1', 'The Banned Band')], new AlbumData('album-1', 'Forbidden Album'), 180000, false, $isrc);
}

beforeEach(function () {
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider([
        blockableTrack('t1'),
        blockableTrack('t1-remaster'),
        new TrackData('fake', 'clean', 'Clean Track', [new ArtistData('artist-9', 'Clean Artist')], new AlbumData('album-9', 'Clean Album'), 180000, false, 'ISRC-CLEAN'),
    ]));
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->member = PartyMember::factory()->for($this->party)->create();
});

function requestBlockable(PartyMember $member, string $trackId)
{
    Sanctum::actingAs($member->user);

    return test()->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => $trackId]);
}

it('refuses a request matching an entry of every match type', function (BlocklistMatchType $type, string $value, bool $regex) {
    BlocklistEntry::factory()->for($this->party)->matching($type, $value)->state(['is_regex' => $regex])->create();
    Event::fake([RequestRejectedEvent::class]);

    requestBlockable($this->member, 't1')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'That track is blocked in this party.');

    expect(TrackRequest::query()->count())->toBe(0);
    Event::assertDispatched(RequestRejectedEvent::class);
})->with([
    'track name' => [BlocklistMatchType::TrackName, 'midnight song T1', false],
    'track id' => [BlocklistMatchType::TrackId, 't1', false],
    'artist name' => [BlocklistMatchType::ArtistName, 'the banned band', false],
    'artist id' => [BlocklistMatchType::ArtistId, 'artist-1', false],
    'album name' => [BlocklistMatchType::AlbumName, 'FORBIDDEN ALBUM', false],
    'album id' => [BlocklistMatchType::AlbumId, 'album-1', false],
    'isrc' => [BlocklistMatchType::Isrc, 'isrc-a', false],
    'track name regex' => [BlocklistMatchType::TrackName, '^midnight\s+song', true],
    'artist name regex' => [BlocklistMatchType::ArtistName, 'banned (band|group)$', true],
    'album name regex' => [BlocklistMatchType::AlbumName, 'forbid+en', true],
]);

it('does not block a request that matches no entry', function () {
    BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::ArtistName, 'Someone Else')->create();

    requestBlockable($this->member, 'clean')->assertSuccessful();
});

it('does not treat a non-regex name entry as a pattern or a substring', function () {
    BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::TrackName, 'Midnight')->create();
    BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::ArtistName, 'The Banned.*')->create();

    requestBlockable($this->member, 't1')->assertSuccessful();
});

it('does not block when the matching entry is disabled', function () {
    BlocklistEntry::factory()->for($this->party)->disabled()->matching(BlocklistMatchType::TrackId, 't1')->create();

    requestBlockable($this->member, 't1')->assertSuccessful();
});

it('does not apply another party blocklist', function () {
    BlocklistEntry::factory()->for(Party::factory()->live()->create())->matching(BlocklistMatchType::TrackId, 't1')->create();

    requestBlockable($this->member, 't1')->assertSuccessful();
});

it('blocks an ISRC across different releases of the track', function () {
    BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::Isrc, 'ISRC-A')->create();

    requestBlockable($this->member, 't1-remaster')->assertUnprocessable();
    requestBlockable($this->member, 't1')->assertUnprocessable();
});

it('applies the blocklist to a Host as well', function () {
    BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::TrackId, 't1')->create();
    $host = PartyMember::factory()->for($this->party)->host()->create();

    requestBlockable($host, 't1')->assertUnprocessable();
});

it('excludes blocked tracks from the Fallback Playlist playable count', function () {
    $tracks = [];
    foreach (range(1, 20) as $i) {
        $tracks[] = new TrackData('fake', "p{$i}", "Playlist {$i}", [new ArtistData("pa{$i}", "Playlist Artist {$i}")], new AlbumData("pal{$i}", "Playlist Album {$i}"), 180000, false, "ISRC-P{$i}");
    }
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider(playlistTracks: ['pl' => $tracks]));
    $this->party->forceFill(['fallback_playlist_id' => 'pl'])->save();
    $gate = app(FallbackPlaylistGate::class);

    expect($gate->check($this->party)->playable)->toBe(20);

    $entry = BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::Isrc, 'ISRC-P3')->create();
    expect($gate->check($this->party)->playable)->toBe(19);

    BlocklistEntry::factory()->for($this->party)->regex()->matching(BlocklistMatchType::TrackName, '^Playlist 1\d$')->create();
    expect($gate->check($this->party)->playable)->toBe(9);

    $entry->forceFill(['is_enabled' => false])->save();
    expect($gate->check($this->party)->playable)->toBe(10);
});

<?php

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Queue\BlocklistMatchType;
use App\Models\BlocklistEntry;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function gateTrack(int $n, string $name): TrackData
{
    return new TrackData('fake', "t{$n}", $name, [new ArtistData('a', 'Artist')], new AlbumData('al', 'Album'), 180000, false, null, [], true);
}

function invalidLogCount(): int
{
    return PartyLogEntry::query()->where('action', 'party.fallback_playlist_invalid')->count();
}

beforeEach(function () {
    $this->withoutVite();
    $this->party = Party::factory()->live()->create(['code' => 'ABCD', 'fallback_playlist_id' => 'pl']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create()->user;
    $tracks = array_map(fn (int $n): TrackData => gateTrack($n, match (true) {
        $n <= 5 => 'Banned', $n === 6 => 'Spare', default => "Fine {$n}"
    }), range(1, 22));
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider(playlistTracks: ['pl' => $tracks]));
    $this->actingAs($this->host);
});

it('warns and logs when adding an entry drops the Fallback Playlist below 20 playable Tracks', function () {
    $this->post(route('parties.blocklist.store', 'ABCD'), ['match_type' => 'track_name', 'value' => 'Banned'])
        ->assertRedirect()
        ->assertSessionHas('warningMessage', 'The Fallback Playlist has only 17 of 20 required playable Tracks.');

    expect(PartyLogEntry::query()->where('action', 'party.fallback_playlist_invalid')->sole()->details)->toBe(['playable' => 17, 'required' => 20]);
});

it('stays quiet when enough playable Tracks remain', function () {
    $this->post(route('parties.blocklist.store', 'ABCD'), ['match_type' => 'track_name', 'value' => 'Spare'])
        ->assertRedirect()
        ->assertSessionMissing('warningMessage');

    expect(invalidLogCount())->toBe(0);
});

it('does not revalidate when a disabled entry is added or no Fallback Playlist is set', function () {
    $this->post(route('parties.blocklist.store', 'ABCD'), ['match_type' => 'track_name', 'value' => 'Banned', 'is_enabled' => false])
        ->assertSessionMissing('warningMessage');

    $this->party->forceFill(['fallback_playlist_id' => null])->save();
    $this->post(route('parties.blocklist.store', 'ABCD'), ['match_type' => 'track_name', 'value' => 'Banned'])
        ->assertSessionMissing('warningMessage');

    expect(invalidLogCount())->toBe(0);
});

it('warns when enabling an entry or changing its value, but not when only notes change', function () {
    $entry = BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::TrackName, 'Nothing')->create(['is_enabled' => false]);
    $url = route('parties.blocklist.update', ['ABCD', $entry]);

    $this->put($url, ['match_type' => 'track_name', 'value' => 'Nothing', 'is_enabled' => false, 'notes' => 'hi'])
        ->assertSessionMissing('warningMessage');
    expect(invalidLogCount())->toBe(0);

    $this->put($url, ['match_type' => 'track_name', 'value' => 'Banned', 'is_enabled' => true])
        ->assertSessionHas('warningMessage');
    expect(invalidLogCount())->toBe(1);

    $this->put($url, ['match_type' => 'track_name', 'value' => 'Banned', 'is_enabled' => true, 'notes' => 'again'])
        ->assertSessionMissing('warningMessage');
    expect(invalidLogCount())->toBe(1);
});

it('stays quiet when disabling or removing an entry restores the count', function () {
    $entry = BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::TrackName, 'Banned')->create();
    $url = route('parties.blocklist.update', ['ABCD', $entry]);

    $this->put($url, ['match_type' => 'track_name', 'value' => 'Banned', 'is_enabled' => false])
        ->assertSessionMissing('warningMessage');
    expect(invalidLogCount())->toBe(0);

    $this->put($url, ['match_type' => 'track_name', 'value' => 'Banned', 'is_enabled' => true])
        ->assertSessionHas('warningMessage');
    $this->delete(route('parties.blocklist.destroy', ['ABCD', $entry]))
        ->assertSessionMissing('warningMessage');

    expect(invalidLogCount())->toBe(1);
});

it('warns when removing an entry still leaves the Fallback Playlist short', function () {
    BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::TrackName, 'Banned')->create();
    $other = BlocklistEntry::factory()->for($this->party)->matching(BlocklistMatchType::TrackName, 'Unrelated')->create();

    $this->delete(route('parties.blocklist.destroy', ['ABCD', $other]))
        ->assertSessionHas('warningMessage', 'The Fallback Playlist has only 17 of 20 required playable Tracks.');
    expect(invalidLogCount())->toBe(1);
});

it('logs for API changes without altering the response', function () {
    Sanctum::actingAs($this->host);

    $this->postJson('/api/v1/parties/ABCD/blocklist', ['match_type' => 'track_name', 'value' => 'Banned'])
        ->assertCreated()->assertJsonMissingPath('meta');

    expect(invalidLogCount())->toBe(1);
});

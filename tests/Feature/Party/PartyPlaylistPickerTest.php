<?php

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\SocialProviders\SpotifyProvider;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\PlaylistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Jobs\StartPlayback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return list<TrackData>
 */
function pickerTracks(int $count): array
{
    return array_map(
        fn (int $n): TrackData => new TrackData('fake', "t{$n}", "Track {$n}", [new ArtistData('a', 'Artist')], new AlbumData('al', 'Album'), 180000, false, null, [], true),
        range(1, $count),
    );
}

/**
 * @return array{Party, User}
 */
function pickerParty(int $tracks = 25): array
{
    Bus::fake([StartPlayback::class]);
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider(
        playlists: [new PlaylistData('plfallback1234', 'Fallback Mix', $tracks), new PlaylistData('plhistory12345', 'History', 0)],
        playlistTracks: ['plfallback1234' => pickerTracks($tracks), 'plhistory12345' => []],
    ));

    $host = User::factory()->create();
    $provider = SocialProvider::query()->where('code', 'fake')->first() ?? new SocialProvider;
    $provider->forceFill(['code' => 'fake', 'name' => 'Fake', 'provider_class' => SpotifyProvider::class])->save();
    LinkedAccount::factory()->for($host)->create(['social_provider_id' => $provider->id]);

    $party = Party::factory()->create(['code' => 'ABCD', 'state' => 'paused', 'user_id' => $host->id, 'music_provider' => 'fake']);
    PartyMember::factory()->for($party)->for($host)->host()->create();

    return [$party, $host];
}

it('saves playlists from a pasted link, re-runs the gate and shows it on the settings page', function () {
    [$party, $host] = pickerParty(25);

    $this->actingAs($host)->put(route('parties.playlists.update', 'ABCD'), [
        'fallback_playlist' => 'https://open.spotify.com/playlist/plfallback1234?si=xyz',
        'history_playlist' => 'spotify:playlist:plhistory12345',
    ])->assertRedirect()->assertSessionHas('fallback_check', ['playable' => 25, 'required' => 20, 'passes' => true]);

    expect($party->refresh()->fallback_playlist_id)->toBe('plfallback1234')
        ->and($party->refresh()->history_playlist_id)->toBe('plhistory12345');

    $this->actingAs($host)->get(route('parties.settings', 'ABCD'))->assertInertia(fn (Assert $page) => $page
        ->where('fallback_playlist_id', 'plfallback1234')
        ->where('history_playlist_id', 'plhistory12345')
        ->where('playlists.0.name', 'Fallback Mix')
        ->where('fallback_check.playable', 25));
});

it('stops appending history when none is chosen, matching the api setting', function () {
    [$party, $host] = pickerParty();
    $party->forceFill(['history_playlist_id' => 'plhistory12345'])->save();

    $this->actingAs($host)->put(route('parties.playlists.update', 'ABCD'), ['fallback_playlist' => 'plfallback1234', 'history_playlist' => ''])->assertRedirect();

    expect($party->refresh()->history_playlist_id)->toBeNull();

    Sanctum::actingAs($host);
    $this->putJson(route('api.v1.parties.playlists.update', $party), ['fallback_playlist_id' => 'plfallback1234', 'history_playlist_id' => null])
        ->assertOk()->assertJsonPath('data.fallback_playlist_id', 'plfallback1234');
});

it('reports a failing gate inline', function () {
    [$party, $host] = pickerParty(5);

    $this->actingAs($host)->put(route('parties.playlists.update', 'ABCD'), ['fallback_playlist' => 'plfallback1234', 'history_playlist' => ''])
        ->assertSessionHas('fallback_check', ['playable' => 5, 'required' => 20, 'passes' => false]);
});

it('rejects garbage, foreign playlists and non-hosts', function () {
    [$party, $host] = pickerParty();

    $this->actingAs($host)->put(route('parties.playlists.update', 'ABCD'), ['fallback_playlist' => 'not a playlist', 'history_playlist' => ''])
        ->assertSessionHasErrors('fallback_playlist');
    $this->actingAs($host)->put(route('parties.playlists.update', 'ABCD'), ['fallback_playlist' => 'someoneelses1234', 'history_playlist' => ''])
        ->assertSessionHasErrors('fallback_playlist_id');

    $this->actingAs(User::factory()->create())->put(route('parties.playlists.update', 'ABCD'), ['fallback_playlist' => 'plfallback1234', 'history_playlist' => ''])
        ->assertForbidden();
    expect($party->refresh()->fallback_playlist_id)->toBeNull();
});

it('lets the host go live from the web UI after picking a 25 track fallback playlist', function () {
    [$party, $host] = pickerParty(25);

    $this->actingAs($host)->post(route('parties.live', 'ABCD'))->assertSessionHasErrors();
    expect($party->refresh()->state)->toBe(PartyState::Paused);

    $this->actingAs($host)->put(route('parties.playlists.update', 'ABCD'), ['fallback_playlist' => 'plfallback1234', 'history_playlist' => ''])->assertRedirect();
    $this->actingAs($host)->post(route('parties.live', 'ABCD'))->assertRedirect();

    expect($party->refresh()->state)->toBe(PartyState::Live);
});

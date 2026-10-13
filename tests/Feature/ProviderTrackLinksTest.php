<?php

use App\Enums\Purpose;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Requests\TrackEligibility;
use App\Services\Spotify\SpotifyGateway;
use Tests\Support\FakeFallbackPlaylist;

beforeEach(function () {
    $this->fake = app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('t1'));
    $this->owner = User::factory()->create(['first_login' => false]);
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
});

function providerLinksSong(Party $party, string $providerId): UpcomingSong
{
    $song = Song::factory()->create(['provider_id' => $providerId, 'provider_track_id' => 't1']);

    return UpcomingSong::factory()->for($party)->for($song)->sentToPlayer()->create(['user_id' => null]);
}

it('names the provider and builds track links inside the provider', function () {
    $spotify = app(MusicProviderFactory::class)->forParty(Party::factory()->create(['provider_id' => 'spotify', 'user_id' => $this->owner->id]), Purpose::Search);

    expect($spotify)->toBeInstanceOf(SpotifyMusicProvider::class)
        ->and($spotify->displayName())->toBe('Spotify')
        ->and($spotify->trackUrl('abc'))->toBe('https://open.spotify.com/track/abc')
        ->and($this->fake->displayName())->toBe('Fake')
        ->and($this->fake->trackUrl('t1'))->toBeNull();
});

it('shows no Spotify links or labels on the upcoming songs pages of a Fake party', function () {
    $party = Party::factory()->create(['provider_id' => 'fake', 'user_id' => $this->owner->id]);
    $upcoming = providerLinksSong($party, 'fake');

    $index = $this->actingAs($this->owner, 'sanctum')->get(route('parties.songs.index', $party->code))->assertOk();
    $show = $this->actingAs($this->owner, 'sanctum')->get(route('parties.songs.show', [$party->code, $upcoming->id]))->assertOk();

    $index->assertSee('Sent to Fake')->assertDontSee('Spotify');
    $show->assertSee('Sent to Fake')->assertDontSee('Spotify')->assertDontSee('open.spotify.com');
});

it('links to Spotify on the upcoming song page of a Spotify party', function () {
    $party = Party::factory()->create(['provider_id' => 'spotify', 'user_id' => $this->owner->id]);
    $upcoming = providerLinksSong($party, 'spotify');

    $this->actingAs($this->owner, 'sanctum')->get(route('parties.songs.show', [$party->code, $upcoming->id]))
        ->assertOk()
        ->assertSee('Sent to Spotify')
        ->assertSee('View on Spotify')
        ->assertSee('https://open.spotify.com/track/t1');
});

it('annotates search results with the party provider name and link', function () {
    $party = Party::factory()->create(['provider_id' => 'fake', 'user_id' => $this->owner->id]);
    $member = PartyMember::factory()->for($party)->for(User::factory())->create();
    $track = $this->fake->getTrack('t1');

    $result = app(TrackEligibility::class)->annotate($party, $member, [$track])[0]->toSearchResult();

    expect($result)->toHaveKey('provider_name', 'Fake')
        ->and($result['external_url'])->toBeNull()
        ->and($result)->not->toHaveKey('external_urls');
});

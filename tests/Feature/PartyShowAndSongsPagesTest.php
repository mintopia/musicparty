<?php

use App\Enums\Purpose;
use App\Models\PlayedSong;
use App\Models\Song;
use App\Models\SongRating;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMapper;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use Database\Seeders\PartyMemberRoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSpotify;

require_once __DIR__.'/../Support/PartyPageHelpers.php';

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PartyMemberRoleSeeder::class);
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
    $this->fake = FakeSpotify::install();
    $this->owner = User::factory()->create();
    pageSearchLinkAccount($this->owner, 'spotify', 'control-token');
    $this->party = pageSearchParty($this->owner);
    $this->member = pageSearchMember($this->party);
    $upcoming = new UpcomingSong;
    $upcoming->party()->associate($this->party);
    $upcoming->song()->associate(Song::fromTrack(SpotifyMapper::track($this->fake->getTrack('t1'))));
    $upcoming->user()->associate($this->member->user);
    $upcoming->score = 4;
    $upcoming->save();
    $this->upcoming = $upcoming;
});

it('renders the show page for a member with the upcoming queue', function () {
    $this->actingAs($this->member->user)->get(route('parties.show', $this->party->code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Show')
            ->where('party.code', $this->party->code)
            ->where('party.canManage', false)
            ->where('member.id', $this->member->id)
            ->where('spotifyNeedsRelink', false)
            ->has('upcoming', 1)
            ->has('playerState')
            ->has('timezone'));
});

it('renders the show page for a manager and answers X-Inertia JSON', function () {
    // A full page visit lets the Inertia middleware register the asset-version resolver, so the
    // simulated XHR below sends the version the server will compute and does not get a 409 reload.
    $this->actingAs($this->owner)->get(route('parties.show', $this->party->code))->assertOk();

    $this->actingAs($this->owner)->get(route('parties.show', $this->party->code), ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia\Inertia::getVersion()])
        ->assertOk()
        ->assertJsonPath('component', 'Parties/Show')
        ->assertJsonPath('props.party.canManage', true);
});

it('lists songs for a manager with sorting and name filter', function () {
    $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'order' => 'votes', 'name' => 'nomatch']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Songs/Index')
            ->where('filters.order', 'votes')
            ->where('songs.total', 0));

    $this->actingAs($this->owner)->get(route('parties.songs.index', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Songs/Index')
            ->where('songs.total', 1)
            ->where('songs.data.0.id', $this->upcoming->id)
            ->where('songs.data.0.score', 4)
            ->has('playerState'));
});

it('filters songs by free-text query and sent status', function () {
    $name = $this->upcoming->song->name;
    $artist = $this->upcoming->song->artists->first()?->name;

    foreach (array_filter([$name, $artist, $this->upcoming->song->album->name]) as $term) {
        $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'q' => $term]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.q', $term)->where('songs.total', 1));
    }

    $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'q' => 'no-such-thing-xyz']))
        ->assertInertia(fn (Assert $page) => $page->where('songs.total', 0));

    $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'type' => 'sent']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.type', 'sent')->where('songs.total', 0));

    $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'type' => 'queued']))
        ->assertInertia(fn (Assert $page) => $page->where('songs.total', 1));

    $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'type' => 'bogus']))
        ->assertSessionHasErrors('type');
});

it('shows a song detail page for a manager', function () {
    $this->actingAs($this->owner)->get(route('parties.songs.show', [$this->party->code, $this->upcoming->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Songs/Show')
            ->where('song.id', $this->upcoming->id)
            ->where('song.requestedBy.nickname', $this->member->user->nickname)
            ->has('votes.data', 0)
            ->has('other', 0)
            ->has('ratings.data', 0)
            ->has('playerState'));
});

it('forbids non-managers from the songs pages', function () {
    $this->actingAs($this->member->user)->get(route('parties.songs.index', $this->party->code))->assertForbidden();
    $this->actingAs($this->member->user)->get(route('parties.songs.show', [$this->party->code, $this->upcoming->id]))->assertForbidden();
});

it('does not serve the show page or songs pages to guests', function () {
    $this->get(route('parties.show', $this->party->code))->assertRedirect();
    $this->get(route('parties.songs.index', $this->party->code))->assertRedirect();
});

it('passes a null myRating when nothing is playing', function () {
    $this->actingAs($this->member->user)->get(route('parties.show', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->where('myRating', null));
});

it('passes the viewer own rating on the current played song as myRating', function () {
    $played = PlayedSong::factory()->for($this->party)->create(['played_at' => now()]);
    $other = pageSearchMember($this->party);

    $this->actingAs($this->member->user)->get(route('parties.show', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->where('myRating', ['playedSongId' => $played->id, 'value' => 0]));

    $played->setRating($this->member->user, -1);
    $played->setRating($other->user, 1);

    $this->actingAs($this->member->user)->get(route('parties.show', $this->party->code))
        ->assertInertia(fn (Assert $page) => $page->where('myRating', ['playedSongId' => $played->id, 'value' => -1]));
    expect(SongRating::count())->toBe(2);
});

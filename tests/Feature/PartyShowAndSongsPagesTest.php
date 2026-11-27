<?php

use App\Enums\AiReviewOutcome;
use App\Enums\Purpose;
use App\Models\AiReview;
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
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', 'sent:yes')->where('songs.total', 0));

    $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'type' => 'queued']))
        ->assertInertia(fn (Assert $page) => $page->where('songs.total', 1));

    $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'type' => 'bogus']))
        ->assertSessionHasErrors('type');
});

it('filters songs by typed tokens, requester and the chip counts', function () {
    $nickname = $this->member->user->nickname;
    $artist = $this->upcoming->song->artists->first()->name;
    $get = fn (string $q) => $this->actingAs($this->owner)->get(route('parties.songs.index', [$this->party->code, 'q' => $q]));

    $get("sent:no by:{$nickname} artist:\"{$artist}\"")->assertInertia(fn (Assert $page) => $page
        ->where('filters.q', "sent:no by:{$nickname} artist:\"{$artist}\"")
        ->where('songs.total', 1)
        ->where('songs.data.0.requestedBy.nickname', $nickname)
        ->where('songs.data.0.requestedBy.isYou', false)
        ->where('counts', ['total' => 1, 'sent' => 0, 'waiting' => 1, 'scheduled' => 0, 'flagged' => 0, 'mine' => 0]));
    $get('sent:yes')->assertInertia(fn (Assert $page) => $page->where('songs.total', 0));
    $get('by:me')->assertInertia(fn (Assert $page) => $page->where('songs.total', 0));
    $get('flagged:yes scheduled:yes')->assertInertia(fn (Assert $page) => $page->where('songs.total', 0));
    $get($nickname)->assertInertia(fn (Assert $page) => $page->where('songs.total', 1));
    $get('artist:no-such-artist')->assertInertia(fn (Assert $page) => $page->where('songs.total', 0));

    $this->upcoming->forceFill(['flagged_at' => now(), 'scheduled_for' => now()->addHour(), 'queued_at' => now()])->save();
    $get('flagged:yes scheduled:yes sent:yes')->assertInertia(fn (Assert $page) => $page
        ->where('songs.total', 1)
        ->where('songs.data.0.flagged', true)
        ->has('songs.data.0.scheduledLabel')
        ->where('counts.flagged', 1)
        ->where('counts.scheduled', 1)
        ->where('counts.sent', 1));
});

it('lets a moderator remove a request from the list', function () {
    $this->actingAs($this->owner)->delete(route('parties.songs.destroy', [$this->party->code, $this->upcoming->id]))
        ->assertRedirect(route('parties.songs.index', $this->party->code));

    expect(UpcomingSong::find($this->upcoming->id))->toBeNull();
});

it('shows a song detail page for a manager', function () {
    $this->actingAs($this->owner)->get(route('parties.songs.show', [$this->party->code, $this->upcoming->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Parties/Songs/Show')
            ->where('song.id', $this->upcoming->id)
            ->where('song.requestedBy.nickname', $this->member->user->nickname)
            ->has('votes.data', 0)
            ->where('aiReview', null)
            ->where('song.removeUrl', route('parties.songs.destroy', [$this->party->code, $this->upcoming->id]))
            ->has('history', 1)
            ->where('history.0.text', $this->member->user->nickname.' requested it')
            ->has('playerState'));
});

it('shows the AI review outcome and a history of what happened on the detail page', function () {
    $this->upcoming->forceFill(['created_at' => now()->subMinutes(5), 'queued_at' => now()])->save();
    $review = AiReview::factory()->outcome(AiReviewOutcome::Flag)->create([
        'party_id' => $this->party->id,
        'upcoming_song_id' => $this->upcoming->id,
        'reason' => 'Possibly explicit',
        'created_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->owner)->get(route('parties.songs.show', [$this->party->code, $this->upcoming->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('aiReview.outcomeLabel', 'Flagged')
            ->where('aiReview.reason', 'Possibly explicit')
            ->where('aiReview.url', route('parties.ai-reviews.show', [$this->party->code, $review->id]))
            ->has('history', 3)
            ->where('history.1.text', 'AI review flagged it for a moderator')
            ->where('history.2.text', 'Sent to the Player'));
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

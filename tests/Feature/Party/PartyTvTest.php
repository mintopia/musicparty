<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the TV screen for guests without authentication', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD', 'name' => 'Friday LAN']);
    $playing = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Playing, 'title' => 'Now Song']);
    $next = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::UpNext, 'title' => 'Next Song']);

    $this->assertGuest();
    $this->withoutVite()->get('/parties/abcd/tv')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Party/Tv')
            ->where('party.code', 'ABCD')
            ->where('party.name', 'Friday LAN')
            ->where('party.joinUrl', route('parties.show', ['party' => 'ABCD']))
            ->where('nowPlaying.id', $playing->id)
            ->where('nowPlaying.track.title', 'Now Song')
            ->where('upNext.id', $next->id)
            ->missing('sequence')
            ->where('startedAt', null));
});

it('renders an empty TV screen when nothing is playing', function () {
    Party::factory()->live()->create(['code' => 'ABCD']);

    $this->withoutVite()->get('/parties/ABCD/tv')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('nowPlaying', null)
            ->where('upNext', null));
});

it('does not expose queue entries or requester accounts beyond the snapshot', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Queued]);

    $this->withoutVite()->get('/parties/ABCD/tv')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->missing('queue')
            ->missing('membership')
            ->missing('canManage'));
});

it('returns not found for an unknown or malformed party code', function (string $code) {
    $this->withoutVite()->get("/parties/{$code}/tv")->assertNotFound();
})->with(['unknown' => 'ZZZZ', 'too long' => 'ABCDE', 'digits' => 'AB12']);

it('reports when the now playing track started', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $playing = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Playing]);
    Play::factory()->for($party)->create(['track_request_id' => $playing->id, 'played_at' => '2026-01-01 00:00:00']);

    $this->withoutVite()->get('/parties/ABCD/tv')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('startedAt', '2026-01-01T00:00:00+00:00'));
});

it('throttles the anonymous TV route per client address', function () {
    Party::factory()->live()->create(['code' => 'ABCD']);

    foreach (range(1, 60) as $attempt) {
        $this->withoutVite()->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get('/parties/abcd/tv')->assertOk();
    }

    $this->withoutVite()->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get('/parties/abcd/tv')->assertTooManyRequests();
    $this->withoutVite()->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])->get('/parties/abcd/tv')->assertOk();
});

it('reads the public route limit from config', function () {
    config(['musicparty.public_routes_per_minute' => 2]);
    Party::factory()->live()->create(['code' => 'ABCD']);

    $this->withoutVite()->get('/parties/abcd/tv')->assertOk();
    $this->withoutVite()->get('/parties/abcd/tv')->assertOk();
    $this->withoutVite()->get('/parties/abcd/tv')->assertTooManyRequests();
});

it('throttles the anonymous API party routes with the same per-address budget', function () {
    config(['musicparty.public_routes_per_minute' => 3]);
    Party::factory()->live()->create(['code' => 'ABCD']);

    $this->getJson('/api/v1/parties/ABCD')->assertOk();
    $this->getJson('/api/v1/parties/ABCD/theme')->assertOk();
    $this->withoutVite()->get('/parties/abcd/tv')->assertOk();

    $this->getJson('/api/v1/parties/ABCD')->assertTooManyRequests();
    $this->getJson('/api/v1/parties/ABCD/theme')->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])->getJson('/api/v1/parties/ABCD')->assertOk();
});

it('carries live like and dislike counts for the playing track without member identifiers', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $playing = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Playing]);
    $play = Play::factory()->for($party)->create(['track_request_id' => $playing->id]);
    $members = PartyMember::factory()->for($party)->count(3)->create();
    Rating::factory()->for($play)->for($members[0], 'member')->create(['value' => 1]);
    Rating::factory()->for($play)->for($members[1], 'member')->create(['value' => 1]);
    Rating::factory()->for($play)->for($members[2], 'member')->create(['value' => -1]);

    $this->withoutVite()->get('/parties/ABCD/tv')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('nowPlaying.likes', 2)
            ->where('nowPlaying.dislikes', 1)
            ->missing('nowPlaying.my_rating')
            ->missing('nowPlaying.ratings')
            ->missing('nowPlaying.members'));
});

it('reports zero counts when the playing track has no ratings', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    $playing = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Playing]);
    Play::factory()->for($party)->create(['track_request_id' => $playing->id]);

    $this->withoutVite()->get('/parties/ABCD/tv')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('nowPlaying.likes', 0)
            ->where('nowPlaying.dislikes', 0));
});

<?php

use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
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

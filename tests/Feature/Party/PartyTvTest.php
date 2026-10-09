<?php

use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the TV screen for guests without authentication', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD', 'name' => 'Friday LAN']);
    $playing = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Playing, 'title' => 'Now Song']);
    $next = TrackRequest::factory()->for($party)->create(['status' => RequestStatus::UpNext, 'title' => 'Next Song']);

    $this->assertGuest();
    $this->withoutVite()->get('/tv/abcd')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Party/Tv')
            ->where('party.code', 'ABCD')
            ->where('party.name', 'Friday LAN')
            ->where('party.joinUrl', route('parties.show', ['party' => 'ABCD']))
            ->where('nowPlaying.id', $playing->id)
            ->where('nowPlaying.track.title', 'Now Song')
            ->where('upNext.id', $next->id)
            ->has('sequence'));
});

it('renders an empty TV screen when nothing is playing', function () {
    Party::factory()->live()->create(['code' => 'ABCD']);

    $this->withoutVite()->get('/tv/ABCD')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('nowPlaying', null)
            ->where('upNext', null));
});

it('does not expose queue entries or requester accounts beyond the snapshot', function () {
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Queued]);

    $this->withoutVite()->get('/tv/ABCD')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->missing('queue')
            ->missing('membership')
            ->missing('canManage'));
});

it('returns not found for an unknown or malformed party code', function (string $code) {
    $this->withoutVite()->get("/tv/{$code}")->assertNotFound();
})->with(['unknown' => 'ZZZZ', 'too long' => 'ABCDE', 'digits' => 'AB12']);

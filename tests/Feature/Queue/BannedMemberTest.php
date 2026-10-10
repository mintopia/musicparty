<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Actions\BanMember;
use App\Domain\Party\Actions\UnbanMember;
use App\Domain\Queue\Actions\RatePlayedSong;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Models\Album;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PlayedSong;
use App\Models\RequestVote;
use App\Models\Song;
use App\Models\SongRating;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
    $this->member = PartyMember::factory()->for($this->party)->create();
    $this->queued = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);
});

function playedSongFor(Party $party): PlayedSong
{
    $album = Album::query()->forceCreate(['name' => 'Album', 'spotify_id' => 'alb-1', 'image_url' => 'https://example.test/a.png']);
    $song = Song::query()->forceCreate(['spotify_id' => 'sp-1', 'name' => 'Song', 'length' => 1000, 'album_id' => $album->id]);

    return PlayedSong::query()->forceCreate(['song_id' => $song->id, 'party_id' => $party->id, 'played_at' => now()]);
}

function rateRequest(User $user, PlayedSong $played, int $rating = 1)
{
    Sanctum::actingAs($user);

    return test()->postJson("/api/v1/parties/ABCD/playedsongs/{$played->id}/rate", ['rating' => $rating]);
}

it('stops a banned member requesting, voting and rating, and restores all three on unban', function () {
    $played = playedSongFor($this->party);
    ($this->app->make(BanMember::class))($this->host->user, $this->party, $this->member);

    Sanctum::actingAs($this->member->user);
    $this->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => 'track-1'])->assertForbidden();
    $this->putJson("/api/v1/parties/ABCD/requests/{$this->queued->id}/vote", ['value' => 'up'])->assertForbidden();
    rateRequest($this->member->user, $played)->assertForbidden()->assertJsonStructure(['message']);

    expect(TrackRequest::query()->count())->toBe(1)
        ->and(RequestVote::query()->count())->toBe(0)
        ->and(SongRating::query()->count())->toBe(0);

    ($this->app->make(UnbanMember::class))($this->host->user, $this->party, $this->member);

    $this->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => 'track-1'])->assertCreated();
    $this->putJson("/api/v1/parties/ABCD/requests/{$this->queued->id}/vote", ['value' => 'up'])->assertOk();
    expect(app(RatePlayedSong::class)($this->party, $this->member->user, $played, 1))->toBeInstanceOf(SongRating::class);
});

it('refuses a banned member through the web request and vote routes', function () {
    $this->member->forceFill(['banned' => true])->save();

    $this->actingAs($this->member->user)->post('/parties/ABCD/requests', ['provider_track_id' => 'track-1'])->assertSessionHasErrors('request');
    $this->actingAs($this->member->user)->put("/parties/ABCD/requests/{$this->queued->id}/vote", ['value' => 'up'])->assertSessionHasErrors('vote');
});

it('lets a banned member still view the party', function () {
    $this->withoutVite();
    $this->member->forceFill(['banned' => true])->save();

    $this->actingAs($this->member->user)->get('/parties/ABCD')->assertOk();
    Sanctum::actingAs($this->member->user);
    $this->getJson('/api/v1/parties/ABCD')->assertOk();
    $this->getJson('/api/v1/parties/ABCD/queue')->assertOk();
});

it('refuses rating by non-members and by banned members in the action', function () {
    $played = playedSongFor($this->party);
    $this->member->forceFill(['banned' => true])->save();
    $action = app(RatePlayedSong::class);

    expect(fn () => $action($this->party, User::factory()->create(), $played, 1))
        ->toThrow(RequestRefusedException::class, 'Join the party to rate tracks.')
        ->and(fn () => $action($this->party, $this->member->user, $played, -1))
        ->toThrow(RequestRefusedException::class, 'banned from rating');
});

it('refuses a non-member rating through the API', function () {
    $played = playedSongFor($this->party);

    rateRequest(User::factory()->create(), $played)->assertForbidden();
});

it('records likes and dislikes from a member in good standing', function () {
    $played = playedSongFor($this->party);
    $action = app(RatePlayedSong::class);

    expect($action($this->party, $this->member->user, $played, 1)?->value)->toBe(1)
        ->and($action($this->party, $this->host->user, $played, 0))->toBeNull();
});

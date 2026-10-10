<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Actions\BanMember;
use App\Domain\Membership\Actions\UnbanMember;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Jobs\StartPlayback;
use App\Domain\Queue\Actions\RatePlay;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\RequestVote;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([StartPlayback::class]);
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
    $this->member = PartyMember::factory()->for($this->party)->create();
    $this->queued = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);
});

function playFor(Party $party): Play
{
    return Play::factory()->create(['party_id' => $party->id]);
}

function rateRequest(User $user, Play $play, string $value = 'up')
{
    Sanctum::actingAs($user);

    return test()->putJson("/api/v1/parties/ABCD/plays/{$play->id}/rating", ['value' => $value]);
}

it('stops a banned member requesting, voting and rating, and restores all three on unban', function () {
    $play = playFor($this->party);
    ($this->app->make(BanMember::class))($this->host->user, $this->party, $this->member);

    Sanctum::actingAs($this->member->user);
    $this->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => 'track-1'])->assertForbidden();
    $this->putJson("/api/v1/parties/ABCD/requests/{$this->queued->id}/vote", ['value' => 'up'])->assertForbidden();
    rateRequest($this->member->user, $play)->assertForbidden()->assertJsonStructure(['message']);

    expect(TrackRequest::query()->count())->toBe(1)
        ->and(RequestVote::query()->count())->toBe(0)
        ->and(Rating::query()->count())->toBe(0);

    ($this->app->make(UnbanMember::class))($this->host->user, $this->party, $this->member);

    $this->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => 'track-1'])->assertCreated();
    $this->putJson("/api/v1/parties/ABCD/requests/{$this->queued->id}/vote", ['value' => 'up'])->assertOk();
    expect((int) app(RatePlay::class)($this->member, $play, VoteDirection::Up)->my_rating)->toBe(1);
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

it('refuses rating by banned members in the action', function () {
    $play = playFor($this->party);
    $this->member->forceFill(['banned' => true])->save();
    $action = app(RatePlay::class);

    expect(fn () => $action($this->member, $play, VoteDirection::Down))
        ->toThrow(RequestRefusedException::class, 'banned');
});

it('refuses a non-member rating through the API', function () {
    $play = playFor($this->party);

    rateRequest(User::factory()->create(), $play)->assertForbidden();
});

it('records likes and dislikes from a member in good standing', function () {
    $play = playFor($this->party);
    $action = app(RatePlay::class);

    expect((int) $action($this->member, $play, VoteDirection::Up)->my_rating)->toBe(1)
        ->and((int) $action($this->member, $play, VoteDirection::Down)->my_rating)->toBe(-1)
        ->and($action($this->member, $play, null)->my_rating)->toBeNull();
});

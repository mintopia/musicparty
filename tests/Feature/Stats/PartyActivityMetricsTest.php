<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Actions\RatePlay;
use App\Domain\Queue\Events\RatingCast;
use App\Domain\Queue\Events\RequestCreated;
use App\Domain\Queue\Events\VoteCast;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\VoteDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function scrapePartyActivity(): string
{
    app()->forgetScopedInstances();
    config(['prometheus.token' => 'scrape-token']);

    return test()->withToken('scrape-token')->get('/'.ltrim(config('prometheus.urls.default'), '/'))->assertOk()->getContent();
}

beforeEach(function () {
    $this->party = Party::factory()->create(['code' => 'ABCD']);
    $this->member = PartyMember::factory()->create(['party_id' => $this->party->id]);
});

it('counts member and system requests separately', function () {
    $memberRequest = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->member->id]);
    $systemRequest = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => null]);

    RequestCreated::dispatch($this->party, $memberRequest);
    RequestCreated::dispatch($this->party, $memberRequest);
    RequestCreated::dispatch($this->party, $systemRequest);

    expect(scrapePartyActivity())
        ->toContain('musicparty_party_requests_total{party="ABCD",source="member"} 2')
        ->toContain('musicparty_party_requests_total{party="ABCD",source="system"} 1');
});

it('counts up and down votes but not retractions', function () {
    $request = TrackRequest::factory()->create(['party_id' => $this->party->id]);

    VoteCast::dispatch($this->party, $request, $this->member, VoteDirection::Up);
    VoteCast::dispatch($this->party, $request, $this->member, VoteDirection::Down);
    VoteCast::dispatch($this->party, $request, $this->member, VoteDirection::Down);
    VoteCast::dispatch($this->party, $request, $this->member, null);

    expect(scrapePartyActivity())
        ->toContain('musicparty_party_votes_total{party="ABCD",direction="up"} 1')
        ->toContain('musicparty_party_votes_total{party="ABCD",direction="down"} 2');
});

it('counts likes and dislikes raised by rating a play', function () {
    $request = TrackRequest::factory()->create(['party_id' => $this->party->id]);
    $play = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $request->id]);
    $other = PartyMember::factory()->create(['party_id' => $this->party->id]);
    $rate = app(RatePlay::class);

    $rate($this->member, $play, VoteDirection::Up);
    $rate($other, $play, VoteDirection::Down);

    expect(scrapePartyActivity())
        ->toContain('musicparty_party_ratings_total{party="ABCD",rating="like"} 1')
        ->toContain('musicparty_party_ratings_total{party="ABCD",rating="dislike"} 1');
});

it('does not dispatch or count a retracted or repeated rating', function () {
    $request = TrackRequest::factory()->create(['party_id' => $this->party->id]);
    $play = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $request->id]);
    $rate = app(RatePlay::class);
    $rate($this->member, $play, VoteDirection::Up);

    Event::fake([RatingCast::class]);
    $rate($this->member, $play, VoteDirection::Up);
    $rate($this->member, $play, null);

    Event::assertNotDispatched(RatingCast::class);
});

it('dispatches RatingCast with the party and direction', function () {
    $request = TrackRequest::factory()->create(['party_id' => $this->party->id]);
    $play = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $request->id]);
    Event::fake([RatingCast::class]);

    app(RatePlay::class)($this->member, $play, VoteDirection::Down);

    Event::assertDispatched(RatingCast::class, fn (RatingCast $event): bool => $event->party->is($this->party)
        && $event->direction === VoteDirection::Down
        && $event->member->is($this->member));
});

it('keeps counts separate per party', function () {
    $otherParty = Party::factory()->create(['code' => 'WXYZ']);
    $request = TrackRequest::factory()->create(['party_id' => $this->party->id]);

    VoteCast::dispatch($this->party, $request, $this->member, VoteDirection::Up);
    VoteCast::dispatch($otherParty, $request, $this->member, VoteDirection::Up);

    expect(scrapePartyActivity())
        ->toContain('musicparty_party_votes_total{party="ABCD",direction="up"} 1')
        ->toContain('musicparty_party_votes_total{party="WXYZ",direction="up"} 1');
});

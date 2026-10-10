<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Http\Resources\V1\QueueEntryResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    $this->track = TrackRequest::factory()->for($this->party)->status(RequestStatus::Playing)->create();
    $this->play = Play::factory()->for($this->party)->create(['track_request_id' => $this->track->id]);
    Sanctum::actingAs($this->user);
});

function playingRatingUrl(Play $play): string
{
    return "/api/v1/parties/ABCD/plays/{$play->id}/rating";
}

it('likes the playing track through its play and shows it in history', function () {
    Queue::fake();

    $this->putJson(playingRatingUrl($this->play), ['value' => 'up'])->assertOk()
        ->assertJsonPath('data.likes', 1)
        ->assertJsonPath('data.my_rating', 1);

    expect(Rating::query()->where('play_id', $this->play->id)->where('party_member_id', $this->member->id)->value('value'))->toBe(1);
    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()
        ->assertJsonPath('data.0.id', $this->play->id)
        ->assertJsonPath('data.0.likes', 1)
        ->assertJsonPath('data.0.my_rating', 1);
    Queue::assertPushed(BroadcastPartyQueue::class, fn ($job) => $job->partyCode === 'ABCD');
});

it('rebroadcasts the queue only when a rating of the playing track changes', function (string $method, ?string $value, bool $broadcasts) {
    $this->putJson(playingRatingUrl($this->play), ['value' => 'up']);
    Queue::fake();

    $this->json($method, playingRatingUrl($this->play), $value === null ? [] : ['value' => $value])->assertOk();

    $broadcasts
        ? Queue::assertPushed(BroadcastPartyQueue::class, fn ($job) => $job->partyCode === 'ABCD')
        : Queue::assertNothingPushed();
})->with([
    'same value' => ['PUT', 'up', false],
    'changed value' => ['PUT', 'down', true],
    'retract' => ['DELETE', null, true],
]);

it('does not rebroadcast retracting a rating that does not exist', function () {
    Queue::fake();

    $this->deleteJson(playingRatingUrl($this->play))->assertOk();

    Queue::assertNothingPushed();
});

it('does not rebroadcast when the rated play is not the playing track', function () {
    Queue::fake();
    $played = TrackRequest::factory()->for($this->party)->status(RequestStatus::Played)->create();
    $play = Play::factory()->for($this->party)->create(['track_request_id' => $played->id]);
    $standalone = Play::factory()->for($this->party)->create();

    $this->putJson(playingRatingUrl($play), ['value' => 'up'])->assertOk();
    $this->putJson(playingRatingUrl($standalone), ['value' => 'up'])->assertOk();

    Queue::assertNothingPushed();
});

it('returns not found for the removed request rating urls', function () {
    $api = "/api/v1/parties/ABCD/requests/{$this->track->id}/rating";
    $web = "/parties/ABCD/requests/{$this->track->id}/rating";

    $this->putJson($api, ['value' => 'up'])->assertNotFound();
    $this->postJson($api, ['value' => 'up'])->assertNotFound();
    $this->deleteJson($api)->assertNotFound();
    $this->actingAs($this->user)->put($web, ['value' => 'up'])->assertNotFound();
    $this->post($web, ['value' => 'up'])->assertNotFound();
    $this->delete($web)->assertNotFound();

    expect(Rating::query()->count())->toBe(0);
});

it('reflects ratings in the queue snapshot', function () {
    $others = PartyMember::factory()->for($this->party)->count(3)->create();
    Rating::factory()->for($this->play)->for($others[0], 'member')->create(['value' => 1]);
    Rating::factory()->for($this->play)->for($others[1], 'member')->create(['value' => -1]);
    Rating::factory()->for($this->play)->for($others[2], 'member')->create(['value' => -1]);

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect($snapshot['now_playing'])->toMatchArray(['likes' => 1, 'dislikes' => 2]);
});

it('reports zero counts in the snapshot for a request without a play', function () {
    $queued = TrackRequest::factory()->for($this->party)->create();

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect($snapshot['queue'][0])->toMatchArray(['id' => $queued->id, 'likes' => 0, 'dislikes' => 0]);
});

it('emits ratings from the queue entry resource when the play summary is loaded', function () {
    Rating::factory()->for($this->play)->for($this->member, 'member')->create(['value' => -1]);

    $track = TrackRequest::query()
        ->whereKey($this->track->id)
        ->with(['play' => fn ($query) => $query->withRatingSummary($this->member)])
        ->firstOrFail();

    expect(new QueueEntryResource($track)->resolve())
        ->toMatchArray(['likes' => 0, 'dislikes' => 1, 'my_rating' => -1]);

    expect(new QueueEntryResource($this->track->fresh())->resolve())
        ->not->toHaveKeys(['likes', 'dislikes', 'my_rating']);
});

it('rates through the web play routes', function () {
    $this->actingAs($this->user)->from('/parties/ABCD')
        ->put("/parties/ABCD/plays/{$this->play->id}/rating", ['value' => 'up'])
        ->assertRedirect('/parties/ABCD');

    expect(Rating::query()->value('value'))->toBe(1);

    $this->delete("/parties/ABCD/plays/{$this->play->id}/rating")->assertSessionHasNoErrors();
    expect(Rating::query()->count())->toBe(0);
});

it('has no separate now-playing rating table', function () {
    expect(Schema::hasTable('play_ratings'))->toBeFalse();
});

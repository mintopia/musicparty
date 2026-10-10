<?php

use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\RequestStatus;
use App\Jobs\BroadcastPartyQueue;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Play;
use App\Models\PlayRating;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    $this->track = TrackRequest::factory()->for($this->party)->status(RequestStatus::Playing)->create();
    Sanctum::actingAs($this->user);
});

function requestRatingUrl(TrackRequest $track, string $code = 'ABCD'): string
{
    return "/api/v1/parties/{$code}/requests/{$track->id}/rating";
}

function ratingsFor(TrackRequest $track): int
{
    return PlayRating::query()->where('track_request_id', $track->id)->count();
}

it('likes the now playing track', function () {
    Queue::fake();

    $this->putJson(requestRatingUrl($this->track), ['value' => 'up'])->assertOk()
        ->assertJsonPath('data.likes', 1)
        ->assertJsonPath('data.dislikes', 0)
        ->assertJsonPath('data.my_rating', 1);

    Queue::assertPushed(BroadcastPartyQueue::class, fn ($job) => $job->partyCode === 'ABCD');
});

it('dislikes the now playing track', function () {
    $this->putJson(requestRatingUrl($this->track), ['value' => 'down'])->assertOk()
        ->assertJsonPath('data.likes', 0)
        ->assertJsonPath('data.dislikes', 1)
        ->assertJsonPath('data.my_rating', -1);
});

it('changes a rating while keeping a single row', function () {
    $this->putJson(requestRatingUrl($this->track), ['value' => 'up']);

    $this->putJson(requestRatingUrl($this->track), ['value' => 'down'])->assertOk()
        ->assertJsonPath('data.likes', 0)
        ->assertJsonPath('data.dislikes', 1)
        ->assertJsonPath('data.my_rating', -1);

    expect(ratingsFor($this->track))->toBe(1);
});

it('retracts a rating', function () {
    $this->putJson(requestRatingUrl($this->track), ['value' => 'up']);

    $this->deleteJson(requestRatingUrl($this->track))->assertOk()
        ->assertJsonPath('data.likes', 0)
        ->assertJsonPath('data.my_rating', 0);

    expect(ratingsFor($this->track))->toBe(0);
});

it('treats a repeated identical rating or a retract of nothing as a no-op without broadcasting', function () {
    $this->putJson(requestRatingUrl($this->track), ['value' => 'up']);
    Queue::fake();

    $this->putJson(requestRatingUrl($this->track), ['value' => 'up'])->assertOk()->assertJsonPath('data.likes', 1);
    $this->deleteJson(requestRatingUrl($this->track))->assertOk();
    Queue::assertPushed(BroadcastPartyQueue::class, 1);

    Queue::fake();
    $this->deleteJson(requestRatingUrl($this->track))->assertOk();
    Queue::assertNothingPushed();
});

it('counts ratings from several members', function () {
    PlayRating::factory()->for($this->track, 'request')->create();
    PlayRating::factory()->for($this->track, 'request')->create();
    PlayRating::factory()->for($this->track, 'request')->dislike()->create();

    $this->putJson(requestRatingUrl($this->track), ['value' => 'up'])->assertOk()
        ->assertJsonPath('data.likes', 3)
        ->assertJsonPath('data.dislikes', 1)
        ->assertJsonPath('data.my_rating', 1);
});

it('refuses a banned member', function () {
    $this->member->forceFill(['banned' => true])->save();
    Queue::fake();

    $this->putJson(requestRatingUrl($this->track), ['value' => 'up'])->assertForbidden();

    expect(ratingsFor($this->track))->toBe(0);
    Queue::assertNothingPushed();
});

it('refuses a user who is not a member', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson(requestRatingUrl($this->track), ['value' => 'up'])->assertForbidden();
});

it('refuses requests that are not playing', function (RequestStatus $status) {
    $track = TrackRequest::factory()->for($this->party)->status($status)->create();

    $this->putJson(requestRatingUrl($track), ['value' => 'up'])->assertStatus(409);
    $this->deleteJson(requestRatingUrl($track))->assertStatus(409);

    expect(ratingsFor($track))->toBe(0);
})->with([RequestStatus::Queued, RequestStatus::UpNext, RequestStatus::Played]);

it('enforces one rating per member at the database level', function () {
    PlayRating::factory()->create(['track_request_id' => $this->track->id, 'party_member_id' => $this->member->id]);

    expect(fn () => PlayRating::factory()->create(['track_request_id' => $this->track->id, 'party_member_id' => $this->member->id]))
        ->toThrow(QueryException::class);
});

it('returns not found for a request from another party', function () {
    Party::factory()->live()->create(['code' => 'WXYZ']);

    $this->putJson(requestRatingUrl($this->track, 'WXYZ'), ['value' => 'up'])->assertNotFound();
});

it('validates the value', function () {
    $this->putJson(requestRatingUrl($this->track), ['value' => 'sideways'])->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->putJson(requestRatingUrl($this->track))->assertUnprocessable();
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', '')->putJson(requestRatingUrl($this->track), ['value' => 'up'])->assertUnauthorized();
});

it('exposes like and dislike counts in the queue snapshot', function () {
    PlayRating::factory()->for($this->track, 'request')->create();
    PlayRating::factory()->for($this->track, 'request')->dislike()->create();
    PlayRating::factory()->for($this->track, 'request')->dislike()->create();

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect($snapshot['now_playing'])->toMatchArray(['likes' => 1, 'dislikes' => 2]);
});

it('attaches ratings to the play through the track request', function () {
    $rating = PlayRating::factory()->for($this->track, 'request')->create();
    $play = Play::factory()->create(['party_id' => $this->party->id, 'track_request_id' => $this->track->id]);

    expect($play->ratings->pluck('id')->all())->toBe([$rating->id])
        ->and($this->track->ratings->pluck('id')->all())->toBe([$rating->id]);
});

describe('web routes', function () {
    it('rates, changes and retracts through the shared action', function () {
        $url = "/parties/ABCD/requests/{$this->track->id}/rating";

        $this->actingAs($this->user)->from('/parties/ABCD')->put($url, ['value' => 'up'])->assertRedirect('/parties/ABCD');
        expect(PlayRating::query()->value('value'))->toBe(1);

        $this->put($url, ['value' => 'down'])->assertSessionHasNoErrors();
        expect(PlayRating::query()->value('value'))->toBe(-1);

        $this->delete($url)->assertSessionHasNoErrors();
        expect(ratingsFor($this->track))->toBe(0);
    });

    it('flashes the refusal for a banned member or a non-playing request', function () {
        $queued = TrackRequest::factory()->for($this->party)->create();

        $this->actingAs($this->user)->from('/parties/ABCD')
            ->put("/parties/ABCD/requests/{$queued->id}/rating", ['value' => 'up'])
            ->assertSessionHasErrors('rating');

        $this->member->forceFill(['banned' => true])->save();
        $this->put("/parties/ABCD/requests/{$this->track->id}/rating", ['value' => 'up'])->assertSessionHasErrors('rating');
        expect(ratingsFor($this->track))->toBe(0);
    });

    it('rejects an invalid value', function () {
        $this->actingAs($this->user)->put("/parties/ABCD/requests/{$this->track->id}/rating", ['value' => 'nope'])
            ->assertSessionHasErrors('value');
    });
});

describe('inertia props', function () {
    it('shares the counts and the member rating with the party page', function () {
        PlayRating::factory()->for($this->track, 'request')->dislike()->create();
        PlayRating::factory()->create(['track_request_id' => $this->track->id, 'party_member_id' => $this->member->id]);

        $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('nowPlaying.likes', 1)
                ->where('nowPlaying.dislikes', 1)
                ->where('myRating', 1));
    });

    it('reports no rating when nothing is playing or the member has not rated', function () {
        $this->withoutVite()->actingAs($this->user)->get('/parties/ABCD')
            ->assertInertia(fn (Assert $page): Assert => $page->where('myRating', 0));

        $this->track->forceFill(['status' => RequestStatus::Played])->save();

        $this->get('/parties/ABCD')->assertInertia(fn (Assert $page): Assert => $page->where('nowPlaying', null)->where('myRating', 0));
    });
});

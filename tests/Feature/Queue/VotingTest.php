<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Queue\RequestStatus;
use App\Jobs\BroadcastPartyQueue;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->startOfHour()->addMinutes(30));
    $this->party = Party::factory()->live()->create(['code' => 'ABCD', 'downvotes' => true, 'downvotes_per_hour' => null]);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    $this->track = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);
    Sanctum::actingAs($this->user);
});

function voteUrl(TrackRequest $track, string $code = 'ABCD'): string
{
    return "/api/v1/parties/{$code}/requests/{$track->id}/vote";
}

function castVote(TrackRequest $track, string $value)
{
    return test()->putJson(voteUrl($track), ['value' => $value]);
}

function downvoteCount(PartyMember $member): int
{
    return RequestVote::query()->where('party_member_id', $member->id)->where('value', -1)->count();
}

it('casts an upvote and raises the score by one', function () {
    Queue::fake();

    castVote($this->track, 'up')->assertOk()
        ->assertJsonPath('data.score', 1)
        ->assertJsonPath('data.my_vote', 1);

    Queue::assertPushed(BroadcastPartyQueue::class, fn ($job) => $job->partyCode === 'ABCD');
});

it('moves the score by two when changing up to down and keeps a single vote', function () {
    castVote($this->track, 'up')->assertJsonPath('data.score', 1);

    castVote($this->track, 'down')->assertOk()
        ->assertJsonPath('data.score', -1)
        ->assertJsonPath('data.my_vote', -1);

    expect(RequestVote::query()->where('party_member_id', $this->member->id)->count())->toBe(1);
});

it('treats a repeated identical vote as a no-op without broadcasting', function () {
    castVote($this->track, 'up');
    Queue::fake();

    castVote($this->track, 'up')->assertOk()->assertJsonPath('data.score', 1);

    Queue::assertNothingPushed();
    expect(RequestVote::query()->count())->toBe(1);
});

it('retracts a vote and broadcasts', function () {
    castVote($this->track, 'up');
    Queue::fake();

    $this->deleteJson(voteUrl($this->track))->assertOk()
        ->assertJsonPath('data.score', 0)
        ->assertJsonPath('data.my_vote', 0);

    Queue::assertPushed(BroadcastPartyQueue::class);
    expect(RequestVote::query()->count())->toBe(0);
});

it('treats retracting when no vote exists as a no-op', function () {
    Queue::fake();

    $this->deleteJson(voteUrl($this->track))->assertOk()->assertJsonPath('data.score', 0);

    Queue::assertNothingPushed();
});

it('sums votes from several members', function () {
    RequestVote::factory()->for($this->track, 'request')->create();
    RequestVote::factory()->down()->for($this->track, 'request')->create();

    castVote($this->track, 'up')->assertJsonPath('data.score', 1);
});

it('refuses a banned member', function () {
    $this->member->forceFill(['banned' => true])->save();

    castVote($this->track, 'up')->assertForbidden();
    $this->deleteJson(voteUrl($this->track))->assertForbidden();
    expect(RequestVote::query()->count())->toBe(0);
});

it('refuses a user who has not joined the party', function () {
    Sanctum::actingAs(User::factory()->create());

    castVote($this->track, 'up')->assertForbidden();
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', '')->putJson(voteUrl($this->track), ['value' => 'up'])->assertUnauthorized();
});

it('refuses voting on requests that are not queued', function (RequestStatus $status) {
    $this->track->update(['status' => $status]);

    castVote($this->track, 'up')->assertConflict();
    $this->deleteJson(voteUrl($this->track))->assertConflict();
    expect(RequestVote::query()->count())->toBe(0);
})->with([
    RequestStatus::Pending, RequestStatus::Playing, RequestStatus::Played,
    RequestStatus::Rejected, RequestStatus::Removed,
]);

it('locks the Up Next request against voting and retracting', function () {
    RequestVote::factory()->for($this->track, 'request')->for($this->member, 'member')->create();
    $this->track->update(['status' => RequestStatus::UpNext]);

    castVote($this->track, 'down')->assertConflict()->assertJsonPath('message', fn ($m) => str_contains($m, 'up next'));
    $this->deleteJson(voteUrl($this->track))->assertConflict();
    expect(RequestVote::query()->sole()->value)->toBe(1);
});

it('refuses downvotes when disabled but allows upvotes', function () {
    $this->party->forceFill(['downvotes' => false])->save();

    castVote($this->track, 'down')->assertUnprocessable();
    castVote($this->track, 'up')->assertOk();
    castVote($this->track, 'down')->assertUnprocessable();
    expect(RequestVote::query()->sole()->value)->toBe(1);
});

it('refuses a downvote over the hourly cap and states when it recovers', function () {
    $this->party->forceFill(['downvotes_per_hour' => 2])->save();
    $tracks = TrackRequest::factory()->for($this->party)->count(3)->create(['status' => RequestStatus::Queued]);

    castVote($tracks[0], 'down')->assertOk();
    $this->travelTo(now()->addMinutes(10));
    castVote($tracks[1], 'down')->assertOk();
    $this->travelTo(now()->addMinutes(5));

    $retryAt = now()->subMinutes(15)->addHour();
    castVote($tracks[2], 'down')->assertTooManyRequests()
        ->assertJsonPath('retry_at', $retryAt->toIso8601String())
        ->assertJsonPath('message', fn ($m) => str_contains($m, $retryAt->utc()->format('H:i')));
    expect(downvoteCount($this->member))->toBe(2);
});

it('restores allowance when a downvote is retracted', function () {
    $this->party->forceFill(['downvotes_per_hour' => 1])->save();
    $other = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);

    castVote($this->track, 'down')->assertOk();
    castVote($other, 'down')->assertTooManyRequests();

    $this->deleteJson(voteUrl($this->track))->assertOk();
    castVote($other, 'down')->assertOk();
});

it('restores allowance when a downvote is changed to an upvote', function () {
    $this->party->forceFill(['downvotes_per_hour' => 1])->save();
    $other = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);

    castVote($this->track, 'down')->assertOk();
    castVote($this->track, 'up')->assertOk();
    castVote($other, 'down')->assertOk();
});

it('counts an up to down change as a downvote made at the time of the change', function () {
    $this->party->forceFill(['downvotes_per_hour' => 1])->save();
    $other = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);

    castVote($this->track, 'up')->assertOk();
    $this->travelTo(now()->addMinutes(50));
    castVote($this->track, 'down')->assertOk();
    $this->travelTo(now()->addMinutes(20));

    castVote($other, 'down')->assertTooManyRequests();
});

it('does not consume allowance for a repeated downvote', function () {
    $this->party->forceFill(['downvotes_per_hour' => 1])->save();

    castVote($this->track, 'down')->assertOk();
    castVote($this->track, 'down')->assertOk();
});

it('rolls the cap window so allowance returns after an hour', function () {
    $this->party->forceFill(['downvotes_per_hour' => 1])->save();
    $other = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);

    castVote($this->track, 'down')->assertOk();
    $this->travelTo(now()->addMinutes(59));
    castVote($other, 'down')->assertTooManyRequests();

    $this->travelTo(now()->addMinutes(2));
    castVote($other, 'down')->assertOk();
});

it('applies no cap when downvotes per hour is null', function () {
    TrackRequest::factory()->for($this->party)->count(6)->create(['status' => RequestStatus::Queued])
        ->each(fn ($track) => castVote($track, 'down')->assertOk());

    expect(downvoteCount($this->member))->toBe(6);
});

it('counts the cap per member and per party', function () {
    $this->party->forceFill(['downvotes_per_hour' => 1])->save();
    $otherParty = Party::factory()->live()->create(['code' => 'WXYZ', 'downvotes_per_hour' => 1]);
    $otherMember = PartyMember::factory()->for($otherParty)->for($this->user)->create();
    $elsewhere = TrackRequest::factory()->for($otherParty)->create(['status' => RequestStatus::Queued]);
    $other = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued]);

    $this->putJson(voteUrl($elsewhere, 'WXYZ'), ['value' => 'down'])->assertOk();
    castVote($this->track, 'down')->assertOk();
    castVote($other, 'down')->assertTooManyRequests();

    expect($otherMember->exists)->toBeTrue();
});

it('refuses a zero downvote cap without a retry time', function () {
    $this->party->forceFill(['downvotes_per_hour' => 0])->save();

    castVote($this->track, 'down')->assertTooManyRequests()->assertJsonMissingPath('retry_at');
});

it('returns not found when the request belongs to another party', function () {
    $foreign = TrackRequest::factory()->create(['status' => RequestStatus::Queued]);

    castVote($foreign, 'up')->assertNotFound();
});

it('validates the vote value', function (mixed $value) {
    $this->putJson(voteUrl($this->track), ['value' => $value])->assertUnprocessable()->assertJsonValidationErrors('value');
})->with(['missing' => [null], 'bad string' => ['sideways'], 'number' => [1], 'array' => [['up']]]);

it('reflects votes in the queue listing for the viewer', function () {
    castVote($this->track, 'down');

    $this->getJson('/api/v1/parties/ABCD/queue')->assertOk()
        ->assertJsonPath('data.0.score', -1)
        ->assertJsonPath('data.0.my_vote', -1);
});

describe('web', function () {
    it('casts and retracts a vote and redirects back', function () {
        $url = route('parties.requests.vote.store', ['party' => 'ABCD', 'trackRequest' => $this->track->id]);

        $this->actingAs($this->user)->from('/parties/ABCD')->put($url, ['value' => 'down'])->assertRedirect('/parties/ABCD');
        expect(RequestVote::query()->sole()->value)->toBe(-1);

        $this->actingAs($this->user)->from('/parties/ABCD')
            ->delete(route('parties.requests.vote.destroy', ['party' => 'ABCD', 'trackRequest' => $this->track->id]))
            ->assertRedirect('/parties/ABCD');
        expect(RequestVote::query()->count())->toBe(0);
    });

    it('flashes the refusal message under the vote error key', function () {
        $this->party->forceFill(['downvotes' => false])->save();
        $url = route('parties.requests.vote.store', ['party' => 'ABCD', 'trackRequest' => $this->track->id]);

        $this->actingAs($this->user)->from('/parties/ABCD')->put($url, ['value' => 'down'])
            ->assertRedirect('/parties/ABCD')
            ->assertSessionHasErrors(['vote' => 'Downvotes are disabled for this party.']);
    });

    it('validates the value and requires login', function () {
        $url = route('parties.requests.vote.store', ['party' => 'ABCD', 'trackRequest' => $this->track->id]);

        $this->actingAs($this->user)->put($url, ['value' => 'nope'])->assertSessionHasErrors('value');
        $this->put($url, ['value' => 'up'])->assertRedirect();
    });
});

it('refuses voting in an ended party with no score change and no broadcast', function () {
    $this->party->forceFill(['state' => PartyState::Ended])->save();
    Queue::fake();

    castVote($this->track, 'up')->assertUnprocessable()->assertJsonPath('message', 'This party has ended, so voting is closed.');

    Queue::assertNothingPushed();
    expect(RequestVote::query()->count())->toBe(0);
});

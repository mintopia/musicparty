<?php

use App\Domain\Queue\Actions\AdvanceQueue;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\StatsUpdatedEvent;
use App\Jobs\RefreshPartyStatsJob;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyStat;
use App\Models\Play;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
    $this->alice = PartyMember::factory()->for($this->party)->create();
    $this->bob = PartyMember::factory()->for($this->party)->create();
});

function statsFor(): array
{
    Sanctum::actingAs(test()->alice->user);

    return test()->getJson('/api/v1/parties/ABCD/stats')->assertOk()->json('data');
}

function runStatsJob(): void
{
    app()->call([new RefreshPartyStatsJob(test()->party->id), 'handle']);
}

function playedRequest(PartyMember $member, array $attributes = []): TrackRequest
{
    $request = TrackRequest::factory()->create([
        'party_id' => $member->party_id,
        'party_member_id' => $member->id,
        'status' => RequestStatus::Played,
        'started_at' => now(),
        ...$attributes,
    ]);
    Play::factory()->create([
        'party_id' => $request->party_id,
        'track_request_id' => $request->id,
        'party_member_id' => $request->party_member_id,
        'provider_track_id' => $request->provider_track_id,
        'title' => $request->title,
        'artists' => $request->artists,
        'duration_ms' => $request->duration_ms,
    ]);

    return $request;
}

describe('access', function () {
    it('refuses anonymous callers', function () {
        $this->getJson('/api/v1/parties/ABCD/stats')->assertUnauthorized();
        $this->get('/parties/ABCD/stats')->assertRedirect();
    });

    it('refuses users who have not joined', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/parties/ABCD/stats')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/parties/ABCD/stats')->assertForbidden();
    });

    it('refuses banned members', function () {
        $this->bob->forceFill(['banned' => true])->save();
        Sanctum::actingAs($this->bob->user);

        $this->getJson('/api/v1/parties/ABCD/stats')->assertForbidden();
    });

    it('renders the stats page for members', function () {
        $this->withoutVite()->actingAs($this->alice->user)->get('/parties/ABCD/stats')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Party/Stats')->where('party.code', 'ABCD')->has('stats.top_tracks'),
        );
    });
});

describe('content', function () {
    it('shows empty states for an empty party', function () {
        expect(statsFor())->toBe([
            'top_tracks' => [], 'top_requesters' => [], 'most_upvoted' => [], 'most_downvoted' => [], 'total_time_played_ms' => 0,
        ]);
    });

    it('ranks tracks and requesters and sums time played', function () {
        playedRequest($this->alice, ['provider_track_id' => 'x', 'title' => 'Song X', 'duration_ms' => 1000]);
        playedRequest($this->alice, ['provider_track_id' => 'x', 'title' => 'Song X', 'duration_ms' => 1000]);
        playedRequest($this->bob, ['provider_track_id' => 'y', 'title' => 'Song Y', 'duration_ms' => 3000]);

        $stats = statsFor();

        expect($stats['top_tracks'][0])->toMatchArray(['title' => 'Song X', 'plays' => 2])
            ->and($stats['top_requesters'][0])->toBe(['nickname' => $this->alice->user->nickname, 'plays' => 2])
            ->and($stats['total_time_played_ms'])->toBe(5000);
    });

    it('excludes fallback plays from requesters but counts them elsewhere', function () {
        playedRequest($this->alice, ['duration_ms' => 1000]);
        playedRequest($this->alice, ['party_member_id' => null, 'duration_ms' => 2000, 'title' => 'Fallback']);

        $stats = statsFor();

        expect(collect($stats['top_requesters'])->sum('plays'))->toBe(1)
            ->and($stats['top_tracks'])->toHaveCount(2)
            ->and($stats['total_time_played_ms'])->toBe(3000);
    });

    it('ranks upvoted and downvoted requests and ignores rejected or removed ones', function () {
        $up = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->alice->id, 'title' => 'Up']);
        $down = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->bob->id, 'title' => 'Down']);
        $gone = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->bob->id, 'status' => RequestStatus::Removed]);
        RequestVote::factory()->create(['track_request_id' => $up->id, 'party_member_id' => $this->bob->id, 'value' => 1]);
        RequestVote::factory()->create(['track_request_id' => $up->id, 'party_member_id' => $this->host->id, 'value' => 1]);
        RequestVote::factory()->create(['track_request_id' => $down->id, 'party_member_id' => $this->alice->id, 'value' => -1]);
        RequestVote::factory()->create(['track_request_id' => $gone->id, 'party_member_id' => $this->alice->id, 'value' => 1]);

        $stats = statsFor();

        expect($stats['most_upvoted'])->toHaveCount(1)
            ->and($stats['most_upvoted'][0])->toMatchArray(['title' => 'Up', 'score' => 2, 'requested_by' => $this->alice->user->nickname])
            ->and($stats['most_downvoted'])->toHaveCount(1)
            ->and($stats['most_downvoted'][0])->toMatchArray(['title' => 'Down', 'score' => -1]);
    });

    it('excludes plays whose request was rejected or removed', function () {
        playedRequest($this->alice, ['status' => RequestStatus::Removed]);

        expect(statsFor()['total_time_played_ms'])->toBe(0);
    });

    it('stays visible when paused or ended', function (string $state) {
        $this->party->forceFill(['state' => $state])->save();
        playedRequest($this->alice, ['duration_ms' => 4000]);

        expect(statsFor()['total_time_played_ms'])->toBe(4000);
    })->with(['paused', 'ended']);

    it('does not leak member ids or secrets', function () {
        playedRequest($this->alice);
        $up = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->alice->id]);
        RequestVote::factory()->create(['track_request_id' => $up->id, 'party_member_id' => $this->bob->id]);

        $json = json_encode(statsFor());

        expect($json)->not->toContain('member_id')->not->toContain('email');
    });
});

describe('projection', function () {
    it('updates and broadcasts when requests, votes, plays and decisions happen', function () {
        Event::fake([StatsUpdatedEvent::class]);
        Queue::fake();
        Sanctum::actingAs($this->bob->user);

        $request = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->alice->id, 'status' => RequestStatus::Queued, 'title' => 'Scripted']);
        $this->putJson("/api/v1/parties/ABCD/requests/{$request->id}/vote", ['value' => 'up'])->assertSuccessful();
        Queue::assertPushed(RefreshPartyStatsJob::class);
        runStatsJob();
        Event::assertDispatched(StatsUpdatedEvent::class);
        expect(statsFor()['most_upvoted'][0]['title'])->toBe('Scripted');

        $request->forceFill(['status' => RequestStatus::UpNext, 'started_at' => now()])->save();
        app(AdvanceQueue::class)($this->party, $request->provider_track_id);
        app(AdvanceQueue::class)($this->party, null);
        runStatsJob();
        expect(statsFor()['top_tracks'][0]['title'])->toBe('Scripted')
            ->and(statsFor()['top_requesters'][0]['nickname'])->toBe($this->alice->user->nickname);

        Sanctum::actingAs($this->host->user);
        $other = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->bob->id, 'status' => RequestStatus::Queued]);
        RequestVote::factory()->create(['track_request_id' => $other->id, 'party_member_id' => $this->alice->id]);
        $this->deleteJson("/api/v1/parties/ABCD/requests/{$other->id}")->assertSuccessful();
        runStatsJob();
        expect(collect(statsFor()['most_upvoted'])->pluck('title'))->not->toContain($other->title);
    });

    it('dispatches one delayed default-queue job for a burst of votes and runs no stats query in the request', function () {
        Queue::fake();
        $request = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->alice->id, 'status' => RequestStatus::Queued]);
        $voters = PartyMember::factory()->for($this->party)->count(50)->create();

        DB::enableQueryLog();
        foreach ($voters as $voter) {
            Sanctum::actingAs($voter->user);
            $this->putJson("/api/v1/parties/ABCD/requests/{$request->id}/vote", ['value' => 'up'])->assertSuccessful();
        }
        $queries = collect(DB::getQueryLog())->pluck('query');

        expect($queries->filter(fn (string $sql) => str_contains($sql, 'party_stats')))->toBeEmpty();
        Queue::assertPushed(RefreshPartyStatsJob::class, 1);
        Queue::assertPushedOn('default', RefreshPartyStatsJob::class, fn (RefreshPartyStatsJob $job) => $job->delay === 5);
    });

    it('lets the job run twice without error and keeps one row', function () {
        runStatsJob();
        runStatsJob();

        expect(PartyStat::query()->where('party_id', $this->party->id)->count())->toBe(1);
    });

    it('ignores a job for a deleted party', function () {
        app()->call([new RefreshPartyStatsJob(999999), 'handle']);

        expect(PartyStat::query()->count())->toBe(0);
    });

    it('broadcasts only on the members channel with display names only', function () {
        $event = new StatsUpdatedEvent('ABCD', ['top_requesters' => [['nickname' => 'Alice', 'plays' => 1]]]);

        expect($event->broadcastOn()[0]->name)->toBe('presence-party.ABCD.members')
            ->and(json_encode($event->broadcastWith()))->not->toContain('member_id');
    });
});

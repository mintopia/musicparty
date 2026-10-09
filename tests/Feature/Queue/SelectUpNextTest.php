<?php

use App\Domain\Queue\Actions\SelectUpNext;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');
    $this->party = Party::factory()->live()->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function queuedWithScore(Party $party, int $score, array $attributes = []): TrackRequest
{
    $request = TrackRequest::factory()->for($party)->create($attributes);

    foreach (range(1, abs($score)) as $i) {
        if ($score === 0) {
            break;
        }

        RequestVote::factory()->create([
            'track_request_id' => $request->id,
            'party_member_id' => PartyMember::factory()->for($party),
            'value' => $score > 0 ? 1 : -1,
        ]);
    }

    return $request;
}

it('picks the highest scoring eligible request and records mode and score', function () {
    queuedWithScore($this->party, 3);
    $best = queuedWithScore($this->party, 5);
    queuedWithScore($this->party, 1);

    $selected = app(SelectUpNext::class)($this->party);

    expect($selected->is($best))->toBeTrue()
        ->and($best->fresh()->status)->toBe(RequestStatus::UpNext)
        ->and($best->fresh()->selection_mode)->toBe('deterministic')
        ->and($best->fresh()->selection_score)->toBe(5)
        ->and(TrackRequest::query()->where('status', RequestStatus::Queued)->count())->toBe(2);
});

it('breaks score ties by oldest created_at then lowest id', function () {
    $newer = queuedWithScore($this->party, 2, ['created_at' => now()->subMinute()]);
    $older = queuedWithScore($this->party, 2, ['created_at' => now()->subMinutes(5)]);
    $sameTimeLaterId = queuedWithScore($this->party, 2, ['created_at' => now()->subMinutes(5)]);

    expect(app(SelectUpNext::class)($this->party)->is($older))->toBeTrue();

    $older->update(['status' => RequestStatus::Played]);
    expect(app(SelectUpNext::class)($this->party)->is($sameTimeLaterId))->toBeTrue()
        ->and($newer->fresh()->status)->toBe(RequestStatus::Queued);
});

it('treats unvoted requests as zero and ranks negatives last', function () {
    queuedWithScore($this->party, -2);
    $zero = queuedWithScore($this->party, 0);

    expect(app(SelectUpNext::class)($this->party)->is($zero))->toBeTrue();
});

it('skips requests whose not_before is in the future and picks them once it passes', function () {
    $held = queuedWithScore($this->party, 9, ['not_before' => now()->addMinutes(10)]);
    $ready = queuedWithScore($this->party, 1);

    expect(app(SelectUpNext::class)($this->party)->is($ready))->toBeTrue();

    $ready->update(['status' => RequestStatus::Played]);
    expect(app(SelectUpNext::class)($this->party))->toBeNull();

    CarbonImmutable::setTestNow(now()->addMinutes(11));
    expect(app(SelectUpNext::class)($this->party)->is($held))->toBeTrue();
});

it('never selects pending, rejected, removed or played requests', function (RequestStatus $status) {
    queuedWithScore($this->party, 9, ['status' => $status]);

    expect(app(SelectUpNext::class)($this->party))->toBeNull();
})->with([RequestStatus::Pending, RequestStatus::Rejected, RequestStatus::Removed, RequestStatus::Played]);

it('yields a single Up Next for duplicate or racing selections and ignores later higher scores', function () {
    $first = queuedWithScore($this->party, 1);
    $stale = Party::query()->find($this->party->id);

    $selected = app(SelectUpNext::class)($this->party);
    $racing = app(SelectUpNext::class)($stale);
    queuedWithScore($this->party, 50);
    $afterNewRequest = app(SelectUpNext::class)($this->party);

    expect($selected->is($first))->toBeTrue()
        ->and($racing)->toBeNull()
        ->and($afterNewRequest)->toBeNull()
        ->and(TrackRequest::query()->where('status', RequestStatus::UpNext)->pluck('id')->all())->toBe([$first->id]);
});

it('does nothing for a party that is not live', function () {
    $this->party->forceFill(['state' => 'paused'])->save();
    queuedWithScore($this->party, 1);

    expect(app(SelectUpNext::class)($this->party))->toBeNull();
});

it('writes a selection entry to the party log', function () {
    $request = queuedWithScore($this->party, 2);

    app(SelectUpNext::class)($this->party);

    expect(PartyLogEntry::query()->where('action', 'queue.selected')->first()->details)
        ->toMatchArray(['request_id' => $request->id, 'mode' => 'deterministic', 'score' => 2]);
});

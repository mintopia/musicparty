<?php

use App\Domain\Queue\Actions\SelectUpNext;
use App\Domain\Queue\Randomizer;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\SelectionMode;
use App\Domain\Queue\Testing\SeededRandomizer;
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

function weightedParty(int $seed = 1): Party
{
    app()->instance(Randomizer::class, new SeededRandomizer($seed));

    return Party::factory()->live()->create(['selection_mode' => SelectionMode::Weighted]);
}

function pickRepeatedly(Party $party, int $rounds): array
{
    $counts = [];

    foreach (range(1, $rounds) as $ignored) {
        $selected = app(SelectUpNext::class)($party);
        $counts[$selected->id] = ($counts[$selected->id] ?? 0) + 1;
        $selected->update(['status' => RequestStatus::Queued, 'up_next_at' => null]);
    }

    return $counts;
}

it('defaults parties to deterministic selection', function () {
    expect($this->party->fresh()->selection_mode)->toBe(SelectionMode::Deterministic);
});

it('picks in proportion to positive score in weighted mode', function () {
    $party = weightedParty();
    $nine = queuedWithScore($party, 9);
    $one = queuedWithScore($party, 1);

    $counts = pickRepeatedly($party, 1000);

    expect($counts[$nine->id] + $counts[$one->id])->toBe(1000)
        ->and($counts[$nine->id])->toBeBetween(860, 940)
        ->and($counts[$one->id])->toBeBetween(60, 140);
});

it('repeats the same picks for the same seed and differs for another', function () {
    $sequence = function (int $seed): array {
        $party = weightedParty($seed);
        $requests = [queuedWithScore($party, 3), queuedWithScore($party, 3), queuedWithScore($party, 3)];

        return collect(range(1, 12))->map(function () use ($party, $requests): int {
            $selected = app(SelectUpNext::class)($party);
            $selected->update(['status' => RequestStatus::Queued, 'up_next_at' => null]);

            return array_search($selected->id, array_column($requests, 'id'), true);
        })->all();
    };

    expect($sequence(7))->toBe($sequence(7))->and($sequence(7))->not->toBe($sequence(8));
});

it('never picks zero or negative requests while a positive one exists', function () {
    $party = weightedParty();
    $positive = queuedWithScore($party, 1);
    queuedWithScore($party, 0);
    queuedWithScore($party, -4);

    expect(pickRepeatedly($party, 50))->toBe([$positive->id => 50]);
});

it('falls back to deterministic ordering when no score is positive', function () {
    $party = weightedParty();
    queuedWithScore($party, -3);
    $zero = queuedWithScore($party, 0);

    $selected = app(SelectUpNext::class)($party);

    expect($selected->is($zero))->toBeTrue()
        ->and($zero->fresh()->selection_mode)->toBe('weighted')
        ->and($zero->fresh()->selection_score)->toBe(0);
});

it('skips requests that are not yet eligible in weighted mode', function () {
    $party = weightedParty();
    queuedWithScore($party, 50, ['not_before' => now()->addMinutes(10)]);
    $eligible = queuedWithScore($party, 1, ['not_before' => now()->subMinute()]);

    expect(pickRepeatedly($party, 20))->toBe([$eligible->id => 20]);
});

it('selects nothing in weighted mode when the queue is empty or nothing is eligible', function () {
    $party = weightedParty();
    expect(app(SelectUpNext::class)($party))->toBeNull();

    queuedWithScore($party, 5, ['not_before' => now()->addHour()]);
    expect(app(SelectUpNext::class)($party))->toBeNull();
});

it('logs the weighted mode and score of the chosen request', function () {
    $party = weightedParty();
    $request = queuedWithScore($party, 4);

    app(SelectUpNext::class)($party);

    $entry = PartyLogEntry::query()->where('action', 'queue.selected')->sole();
    expect($entry->details)->toMatchArray(['request_id' => $request->id, 'mode' => 'weighted', 'score' => 4]);
});

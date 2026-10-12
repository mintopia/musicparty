<?php

use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Queue\Actions\SetRequestNotBefore;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\TrackRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->party = Party::factory()->live()->create();
    $this->request = TrackRequest::factory()->create(['party_id' => $this->party->id]);
});

it('sets not_before, logs the system actor and broadcasts the Queue', function () {
    $until = CarbonImmutable::parse('2026-12-02 10:00:00');

    expect(app(SetRequestNotBefore::class)($this->party, $this->request, $until, 'mod:test'))->toBeTrue();

    $entry = PartyLogEntry::query()->where('action', 'request.not_before_set')->sole();
    expect($this->request->fresh()->not_before->equalTo($until))->toBeTrue()
        ->and($entry->system_actor)->toBe('mod:test')
        ->and($entry->details)->toMatchArray(['request_id' => $this->request->id, 'previous_not_before' => null]);
    Queue::assertPushed(BroadcastPartyQueue::class);
});

it('clears not_before with null', function () {
    $this->request->forceFill(['not_before' => CarbonImmutable::parse('2026-12-02 10:00:00')])->save();

    expect(app(SetRequestNotBefore::class)($this->party, $this->request, null, 'mod:test'))->toBeTrue()
        ->and($this->request->fresh()->not_before)->toBeNull();
});

it('does nothing and logs nothing when the value is unchanged', function (?string $value) {
    $current = $value === null ? null : CarbonImmutable::parse($value);
    $this->request->forceFill(['not_before' => $current])->save();

    expect(app(SetRequestNotBefore::class)($this->party, $this->request, $current, 'mod:test'))->toBeFalse()
        ->and(PartyLogEntry::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with(['null' => [null], 'set' => ['2026-12-02 10:00:00']]);

it('ignores a Request from another Party', function () {
    $other = Party::factory()->live()->create();

    expect(app(SetRequestNotBefore::class)($other, $this->request, CarbonImmutable::parse('2026-12-02 10:00:00'), 'mod:test'))->toBeFalse()
        ->and($this->request->fresh()->not_before)->toBeNull();
});

<?php

use App\Domain\Mod\Exceptions\RuleTimeoutException;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\RequestRejectedEvent;
use App\Jobs\StartPlayback;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\Mods\HoldingRuleMod;
use Tests\Fixtures\Mods\ModFixtures;
use Tests\Fixtures\Mods\RejectingRuleMod;
use Tests\Fixtures\Mods\ThrowingRuleMod;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake([StartPlayback::class]);
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['hold_requests' => false]);
    $this->member = PartyMember::factory()->for($this->party)->create();
    $this->request = fn (string $trackId = 'track-1', ?PartyMember $member = null, ?Party $party = null) => app(RequestTrack::class)($party ?? $this->party, $member ?? $this->member, $trackId);
});

it('rejects with the Mod reason, fires the rejection event and logs the Mod, track and reason', function () {
    Event::fake([RequestRejectedEvent::class]);
    ModFixtures::enable($this->party, new RejectingRuleMod);

    expect(fn () => ($this->request)())->toThrow(RequestRefusedException::class, 'Rejecting Mod rejected this request: Not on the playlist theme');

    expect(TrackRequest::query()->count())->toBe(0);
    Event::assertDispatched(RequestRejectedEvent::class);
    $entry = PartyLogEntry::query()->where('action', 'mod.request_rejected')->sole();
    expect($entry->system_actor)->toBe('mod:rejecting')
        ->and($entry->subject)->toBe('Alpha Song')
        ->and($entry->details)->toMatchArray(['mod' => 'Rejecting Mod', 'reason' => 'Not on the playlist theme', 'track' => 'track-1']);
});

it('reports a Mod rejection as a rule violation status', function () {
    ModFixtures::enable($this->party, new RejectingRuleMod);

    try {
        ($this->request)();
    } catch (RequestRefusedException $refusal) {
        expect($refusal->status())->toBe(RequestRefusedException::RULE_VIOLATION);
    }
});

it('holds a request as Pending even when the party does not hold requests, and logs it', function () {
    ModFixtures::enable($this->party, new HoldingRuleMod);

    $outcome = ($this->request)();

    expect($outcome->request->status)->toBe(RequestStatus::Pending);
    $entry = PartyLogEntry::query()->where('action', 'mod.request_held')->sole();
    expect($entry->details)->toMatchArray(['mod' => 'Holding Mod', 'reason' => 'Needs a human look', 'request_id' => $outcome->request->id]);
});

it('accepts when no rule objects', function () {
    ModFixtures::enable($this->party, new RejectingRuleMod(trackId: 'track-2'));

    expect(($this->request)()->request->status)->toBe(RequestStatus::Queued)
        ->and(PartyLogEntry::query()->where('action', 'like', 'mod.%')->count())->toBe(0);
});

it('lets reject win over hold and hold win over accept', function () {
    ModFixtures::enable($this->party, new HoldingRuleMod);
    ModFixtures::enable($this->party, new RejectingRuleMod);

    expect(fn () => ($this->request)())->toThrow(RequestRefusedException::class);
    expect(($this->request)('track-3')->request->status)->toBe(RequestStatus::Queued);
});

it('does not apply Mod rules to a duplicate upvote', function () {
    $other = PartyMember::factory()->for($this->party)->create();
    $existing = ($this->request)();
    ModFixtures::enable($this->party, new RejectingRuleMod);

    $outcome = ($this->request)('track-1', $other);

    expect($outcome->created)->toBeFalse()->and($outcome->request->is($existing->request))->toBeTrue();
});

it('accepts by default when a rule throws and logs the failure', function () {
    ModFixtures::enable($this->party, new ThrowingRuleMod);

    expect(($this->request)()->request->status)->toBe(RequestStatus::Queued);

    $entry = PartyLogEntry::query()->where('action', 'mod.rule_failed')->sole();
    expect($entry->details)->toMatchArray(['mod' => 'Throwing Mod', 'error' => 'classifier exploded']);
});

it('holds when a rule throws and the Mod is set to hold on failure', function () {
    ModFixtures::enable($this->party, new ThrowingRuleMod, ['failure_behaviour' => 'hold']);

    $outcome = ($this->request)();

    expect($outcome->request->status)->toBe(RequestStatus::Pending)
        ->and(PartyLogEntry::query()->where('action', 'mod.rule_failed')->count())->toBe(1)
        ->and(PartyLogEntry::query()->where('action', 'mod.request_held')->count())->toBe(1);
});

it('treats a rule timeout as a failure', function () {
    ModFixtures::enable($this->party, new ThrowingRuleMod(failure: new RuleTimeoutException('timed out')), ['failure_behaviour' => 'hold']);

    expect(($this->request)()->request->status)->toBe(RequestStatus::Pending)
        ->and(PartyLogEntry::query()->where('action', 'mod.rule_failed')->sole()->details['error'])->toBe('timed out');
});

it('keeps the failure log when another Mod then rejects', function () {
    ModFixtures::enable($this->party, new ThrowingRuleMod);
    ModFixtures::enable($this->party, new RejectingRuleMod);

    expect(fn () => ($this->request)())->toThrow(RequestRefusedException::class);
    expect(PartyLogEntry::query()->where('action', 'mod.rule_failed')->count())->toBe(1);
});

it('has no effect when the Mod is disabled', function () {
    $mod = ModFixtures::enable($this->party, new RejectingRuleMod);
    ModFixtures::disable($this->party, $mod);

    expect(($this->request)()->request->status)->toBe(RequestStatus::Queued);
});

it('has no effect for a party that has not enabled the Mod', function () {
    $partyB = Party::factory()->live()->create(['hold_requests' => false]);
    $memberB = PartyMember::factory()->for($partyB)->create();
    ModFixtures::enable($this->party, new RejectingRuleMod);

    expect(($this->request)('track-1', $memberB, $partyB)->request->status)->toBe(RequestStatus::Queued);
});

<?php

use App\Domain\Mod\Actions\RunScheduledActions;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Queue\RequestStatus;
use App\Jobs\RunModScheduledActions;
use App\Models\TrackRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Mods\ModFixtures;
use Tests\Fixtures\Mods\SchedulerMod;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-01-01 12:00:00');
    Queue::fake();
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['explicit' => false]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('creates a member-less system request for a Live party attributed to the Mod in the Party Log', function () {
    ModFixtures::enable($this->party, $mod = new SchedulerMod);

    expect(app(RunScheduledActions::class)($this->party))->toBe(1);

    $request = TrackRequest::query()->sole();
    expect($request->party_member_id)->toBeNull()
        ->and($request->status)->toBe(RequestStatus::Queued)
        ->and($request->provider_track_id)->toBe('track-3');
    $entry = PartyLogEntry::query()->where('action', 'mod.system_request_created')->sole();
    expect($entry->system_actor)->toBe('mod:scheduler')
        ->and($entry->details)->toMatchArray(['mod' => 'Scheduler Mod', 'request_id' => $request->id, 'bypassed_rules' => false]);
});

it('runs only when due per everySeconds', function () {
    ModFixtures::enable($this->party, $mod = new SchedulerMod(every: 60));
    $run = app(RunScheduledActions::class);

    $run($this->party);
    CarbonImmutable::setTestNow('2026-01-01 12:00:30');
    $run($this->party);
    expect($mod->runs)->toBe(1);

    CarbonImmutable::setTestNow('2026-01-01 12:01:01');
    $run($this->party);
    expect($mod->runs)->toBe(2);
});

it('does not run for Paused or Ended parties', function (string $state) {
    $party = Party::factory()->create(['state' => $state]);
    ModFixtures::enable($party, $mod = new SchedulerMod);

    expect(app(RunScheduledActions::class)($party))->toBe(0)->and($mod->runs)->toBe(0);
})->with(['paused', 'ended']);

it('passes system requests through the party rules unless the Mod bypasses them', function (bool $bypass, int $created) {
    ModFixtures::enable($this->party, new SchedulerMod(trackId: 'track-2', bypassRules: $bypass));

    expect(app(RunScheduledActions::class)($this->party))->toBe($created)
        ->and(TrackRequest::query()->count())->toBe($created)
        ->and(PartyLogEntry::query()->where('action', 'mod.system_request_refused')->count())->toBe($created === 0 ? 1 : 0);
})->with([[false, 0], [true, 1]]);

it('does nothing when the Mod is disabled', function () {
    $mod = ModFixtures::enable($this->party, new SchedulerMod);
    ModFixtures::disable($this->party, $mod);

    expect(app(RunScheduledActions::class)($this->party))->toBe(0)->and(TrackRequest::query()->count())->toBe(0);
});

it('does nothing for a party that has not enabled the Mod', function () {
    $partyB = Party::factory()->live()->create();
    ModFixtures::enable($this->party, $mod = new SchedulerMod);

    expect(app(RunScheduledActions::class)($partyB))->toBe(0)->and($mod->runs)->toBe(0);
});

it('does not duplicate a track that is already active', function () {
    ModFixtures::enable($this->party, new SchedulerMod(every: 1));
    $run = app(RunScheduledActions::class);

    $run($this->party);
    CarbonImmutable::setTestNow('2026-01-01 12:00:05');

    expect($run($this->party))->toBe(0)->and(TrackRequest::query()->count())->toBe(1);
});

it('logs a failing action without throwing', function () {
    ModFixtures::enable($this->party, new SchedulerMod(failing: true));

    expect(app(RunScheduledActions::class)($this->party))->toBe(0);
    expect(PartyLogEntry::query()->where('action', 'mod.scheduled_action_failed')->sole()->details['error'])->toBe('schedule exploded');
});

it('runs through the scheduled job for Live parties only', function () {
    $paused = Party::factory()->create(['state' => 'paused']);
    ModFixtures::enable($this->party, new SchedulerMod);
    ModFixtures::enable($paused, new SchedulerMod('scheduler-2'));

    (new RunModScheduledActions)->handle(app(RunScheduledActions::class));

    expect(TrackRequest::query()->pluck('party_id')->all())->toBe([$this->party->id]);
});

it('creates one system request when two runs land in the same second and re-arms after the interval', function () {
    ModFixtures::enable($this->party, $mod = new SchedulerMod(every: 60));

    $created = app(RunScheduledActions::class)($this->party) + app(RunScheduledActions::class)($this->party);

    expect($created)->toBe(1)
        ->and($mod->runs)->toBe(1)
        ->and(TrackRequest::query()->count())->toBe(1);

    CarbonImmutable::setTestNow('2026-01-01 12:00:59');
    app(RunScheduledActions::class)($this->party);
    expect($mod->runs)->toBe(1);

    CarbonImmutable::setTestNow('2026-01-01 12:01:01');
    app(RunScheduledActions::class)($this->party);
    expect($mod->runs)->toBe(2);
});

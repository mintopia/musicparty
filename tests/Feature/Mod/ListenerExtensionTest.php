<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Actions\PauseParty;
use App\Domain\Party\Events\PartyStateChanged;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Actions\AdvanceQueue;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Actions\VoteOnRequest;
use App\Domain\Queue\Events\RequestCreated;
use App\Domain\Queue\Events\TrackEnded;
use App\Domain\Queue\Events\TrackStarted;
use App\Domain\Queue\Events\VoteCast;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use App\Jobs\StartPlayback;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Fixtures\Mods\ListenerMod;
use Tests\Fixtures\Mods\ModFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake([StartPlayback::class]);
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['hold_requests' => false]);
    $this->member = PartyMember::factory()->for($this->party)->create();
    $this->events = [RequestCreated::class, VoteCast::class, TrackStarted::class, TrackEnded::class, PartyStateChanged::class];
});

it('delivers request created, vote cast, track started and ended, and party state events', function () {
    $mod = ModFixtures::enable($this->party, new ListenerMod(events: $this->events));
    $other = PartyMember::factory()->for($this->party)->create();

    $outcome = app(RequestTrack::class)($this->party, $this->member, 'track-1');
    app(VoteOnRequest::class)($this->party, $other, $outcome->request, VoteDirection::Up);
    $outcome->request->forceFill(['status' => RequestStatus::UpNext])->save();
    app(AdvanceQueue::class)($this->party, 'track-1');
    app(AdvanceQueue::class)($this->party, null);
    app(PauseParty::class)(User::factory()->create(), $this->party);

    expect(array_map(fn (object $event): string => $event::class, $mod->received))->toBe([
        RequestCreated::class, VoteCast::class, TrackStarted::class, TrackEnded::class, PartyStateChanged::class,
    ]);
    $state = end($mod->received);
    expect($state->old)->toBe(PartyState::Live)->and($state->new)->toBe(PartyState::Paused);
});

it('only delivers the events a Mod subscribed to', function () {
    $mod = ModFixtures::enable($this->party, new ListenerMod(events: [VoteCast::class]));

    app(RequestTrack::class)($this->party, $this->member, 'track-1');

    expect($mod->received)->toBe([]);
});

it('does not disrupt the operation when a listener throws, and logs the failure', function () {
    ModFixtures::enable($this->party, new ListenerMod(events: [RequestCreated::class], failing: true));

    $outcome = app(RequestTrack::class)($this->party, $this->member, 'track-1');

    expect($outcome->request->status)->toBe(RequestStatus::Queued)
        ->and(TrackRequest::query()->count())->toBe(1);
    $entry = PartyLogEntry::query()->where('action', 'mod.listener_failed')->sole();
    expect($entry->system_actor)->toBe('mod:listener')
        ->and($entry->details)->toMatchArray(['event' => RequestCreated::class, 'error' => 'listener exploded']);
});

it('lets one failing Mod not stop the next Mod', function () {
    ModFixtures::enable($this->party, new ListenerMod('bad', [RequestCreated::class], failing: true));
    $good = ModFixtures::enable($this->party, new ListenerMod('good', [RequestCreated::class]));

    app(RequestTrack::class)($this->party, $this->member, 'track-1');

    expect($good->received)->toHaveCount(1);
});

it('does not deliver to a disabled Mod', function () {
    $mod = ModFixtures::enable($this->party, new ListenerMod(events: [RequestCreated::class]));
    ModFixtures::disable($this->party, $mod);

    app(RequestTrack::class)($this->party, $this->member, 'track-1');

    expect($mod->received)->toBe([]);
});

it('does not deliver another party\'s events', function () {
    $partyB = Party::factory()->live()->create();
    $mod = ModFixtures::enable($partyB, new ListenerMod(events: [RequestCreated::class]));

    app(RequestTrack::class)($this->party, $this->member, 'track-1');

    expect($mod->received)->toBe([]);
});

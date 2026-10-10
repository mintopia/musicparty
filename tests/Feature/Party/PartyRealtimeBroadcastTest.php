<?php

use App\Domain\Membership\Actions\BanMember;
use App\Domain\Membership\Broadcast\MemberBannedEvent;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Actions\EndParty;
use App\Domain\Party\Actions\PauseParty;
use App\Domain\Party\Actions\ReopenParty;
use App\Domain\Party\Broadcast\PartyStateChangedEvent;
use App\Domain\Party\Models\Party;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([PartyStateChangedEvent::class, MemberBannedEvent::class]);
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
});

function assertStateBroadcast(string $state): void
{
    Event::assertDispatched(PartyStateChangedEvent::class, function (PartyStateChangedEvent $event) use ($state): bool {
        $channels = $event->broadcastOn();

        return $event->broadcastAs() === 'party.state_changed'
            && $event->broadcastWith() === ['state' => $state]
            && count($channels) === 1
            && ! $channels[0] instanceof PrivateChannel
            && $channels[0]->name === 'party.ABCD';
    });
}

it('broadcasts the new state on the public channel when pausing, ending and reopening', function () {
    app(PauseParty::class)($this->host->user, $this->party);
    assertStateBroadcast('paused');

    app(EndParty::class)($this->host->user, $this->party);
    assertStateBroadcast('ended');

    Event::assertDispatchedTimes(PartyStateChangedEvent::class, 2);

    app(ReopenParty::class)($this->host->user, $this->party);
    Event::assertDispatchedTimes(PartyStateChangedEvent::class, 3);
});

it('does not broadcast a state change for a refused transition', function () {
    expect(fn () => app(ReopenParty::class)($this->host->user, $this->party))->toThrow(Exception::class);

    Event::assertNotDispatched(PartyStateChangedEvent::class);
});

it('broadcasts member.banned to the banned member channel only', function () {
    $target = PartyMember::factory()->for($this->party)->create();

    app(BanMember::class)($this->host->user, $this->party, $target);

    Event::assertDispatched(MemberBannedEvent::class, function (MemberBannedEvent $event) use ($target): bool {
        $channels = $event->broadcastOn();

        return $event->broadcastAs() === 'member.banned'
            && $event->broadcastWith() === ['member_id' => $target->id]
            && count($channels) === 1
            && $channels[0]->name === "private-party.ABCD.member.{$target->id}";
    });
});

it('does not broadcast member.banned when the ban is refused or already applied', function () {
    app(BanMember::class)($this->host->user, $this->party, PartyMember::factory()->for($this->party)->banned()->create());
    expect(fn () => app(BanMember::class)($this->host->user, $this->party, $this->host))->toThrow(Exception::class);

    Event::assertNotDispatched(MemberBannedEvent::class);
});

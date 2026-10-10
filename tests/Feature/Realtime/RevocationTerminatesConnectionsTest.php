<?php

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Actions\RevokeRole;
use App\Domain\Admin\Actions\SuspendUser;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Actions\BanMember;
use App\Domain\Membership\Actions\ChangeMemberRole;
use App\Domain\Membership\Broadcast\MemberBannedEvent;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Models\Party;
use App\Support\Realtime\Contracts\RealtimeConnections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeRealtimeConnections;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->host = PartyMember::factory()->for($this->party)->host()->create();
    $this->connections = app(RealtimeConnections::class);
});

it('is the fake in tests', function () {
    expect(app(RealtimeConnections::class))->toBeInstanceOf(FakeRealtimeConnections::class);
});

it('terminates a banned member once, after commit, following the banned event', function () {
    $target = PartyMember::factory()->for($this->party)->create();
    $order = [];
    Event::listen(MemberBannedEvent::class, function () use (&$order) {
        $order[] = 'event';
    });
    $this->connections->onTerminate = function () use (&$order) {
        $order[] = 'terminate';
    };

    DB::transaction(function () use ($target, &$order) {
        app(BanMember::class)($this->host->user, $this->party, $target);
        expect($this->connections->terminated)->toBe([]);
        $order[] = 'commit';
    });

    $this->connections->assertTerminatedOnce($target->user_id);
    expect($order)->toBe(['commit', 'event', 'terminate']);
});

it('does not terminate when the ban rolls back', function () {
    $target = PartyMember::factory()->for($this->party)->create();

    try {
        DB::transaction(function () use ($target) {
            app(BanMember::class)($this->host->user, $this->party, $target);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    $this->connections->assertNothingTerminated();
});

it('does not terminate for refused or repeated bans', function () {
    $target = PartyMember::factory()->for($this->party)->banned()->create();

    app(BanMember::class)($this->host->user, $this->party, $target);

    $this->connections->assertNothingTerminated();
});

it('terminates a demoted moderator but not other role changes', function () {
    $moderator = PartyMember::factory()->for($this->party)->moderator()->create();
    $guest = PartyMember::factory()->for($this->party)->create();

    app(ChangeMemberRole::class)($this->host->user, $this->party, $guest, PartyRole::Vip);
    app(ChangeMemberRole::class)($this->host->user, $this->party, $moderator, PartyRole::Moderator);
    $this->connections->assertNothingTerminated();

    app(ChangeMemberRole::class)($this->host->user, $this->party, $moderator, PartyRole::Guest);

    $this->connections->assertTerminatedOnce($moderator->user_id);
});

it('terminates a suspended user once and skips repeats', function () {
    $admin = User::factory()->create();
    app(GrantRole::class)->handle($admin, $admin, 'admin');
    $user = User::factory()->create();

    app(SuspendUser::class)->handle($admin, $user);
    app(SuspendUser::class)->handle($admin, $user);

    $this->connections->assertTerminatedOnce($user->id);
    expect($this->connections->terminated)->toHaveCount(1);
});

it('terminates a user whose role is revoked, and skips users without it', function () {
    $admin = User::factory()->create();
    app(GrantRole::class)->handle($admin, $admin, 'admin');
    $other = User::factory()->create();
    $holder = User::factory()->create();
    app(GrantRole::class)->handle($admin, $holder, 'admin');

    app(RevokeRole::class)->handle($admin, $other, 'admin');
    $this->connections->assertNothingTerminated();

    app(RevokeRole::class)->handle($admin, $holder, 'admin');

    $this->connections->assertTerminatedOnce($holder->id);
});

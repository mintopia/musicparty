<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\QueueSelector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

function scheduledMember(Party $party, string $roleCode, ?User $user = null): User
{
    $user ??= User::factory()->create();
    $member = new PartyMember;
    $member->party()->associate($party);
    $member->user()->associate($user);
    $member->role()->associate(PartyMemberRole::query()->where('code', $roleCode)->first() ?? PartyMemberRole::query()->forceCreate(['code' => $roleCode, 'name' => ucfirst($roleCode)]));
    $member->save();

    return $user;
}

function scheduledPost(Party $party, array $extra = [])
{
    return test()->postJson(route('api.v1.parties.upcomingsongs.store', [$party]), ['provider_track_id' => 't1'] + $extra);
}

function scheduledRequest(Party $party, string $trackId, array $attributes = []): UpcomingSong
{
    $song = Song::factory()->create(['provider_track_id' => $trackId]);

    return UpcomingSong::factory()->for($party)->create(array_merge(['song_id' => $song->id, 'queued_at' => null], $attributes));
}

function scheduledSent(): array
{
    return array_map(fn ($track) => $track->id, app(FakeMusicProvider::class)->sentToPlayer());
}

beforeEach(function () {
    Cache::flush();
    app()->forgetInstance(FakeMusicProvider::class);
    app(FakeMusicProvider::class)->withQueueDepth(0)->withTrack(FakeFallbackPlaylist::track('t1'));
    $this->party = Party::factory()->create(['provider_id' => 'fake', 'active' => true, 'weighted' => false, 'timezone' => 'America/New_York']);
    $this->withoutVite();
    User::query()->update(['first_login' => false]);
});

afterEach(fn () => Carbon::setTestNow());

describe('creating a Scheduled Request', function () {
    it('refuses a member', function () {
        Sanctum::actingAs(scheduledMember($this->party, 'user'));

        scheduledPost($this->party, ['scheduled_for' => '2030-01-01 20:00'])->assertForbidden();

        expect(UpcomingSong::count())->toBe(0);
    });

    it('accepts the party owner and an owner-role member, reading the time in the party timezone', function (string $who) {
        $user = $who === 'creator' ? scheduledMember($this->party, 'user', $this->party->user) : scheduledMember($this->party, 'owner');
        Sanctum::actingAs($user);

        scheduledPost($this->party, ['scheduled_for' => '2030-01-01 20:00'])->assertSuccessful()->assertJsonPath('data.scheduled_for', '2030-01-02T01:00:00+00:00');

        $request = UpcomingSong::sole();
        expect($request->scheduled_for->utc()->toDateTimeString())->toBe('2030-01-02 01:00:00')
            ->and($request->not_before->equalTo($request->scheduled_for))->toBeTrue();
    })->with(['creator', 'owner member']);
});

describe('selecting Scheduled Requests', function () {
    it('holds until its time in the party timezone', function () {
        Carbon::setTestNow('2030-01-02 00:30:00');
        $at = Carbon::parse('2030-01-01 20:00', 'America/New_York')->utc();
        scheduledRequest($this->party, 'SCHED', ['scheduled_for' => $at, 'not_before' => $at]);

        app(QueueSelector::class)->sendNext($this->party);
        expect(scheduledSent())->toBe([]);

        Carbon::setTestNow('2030-01-02 01:00:01');
        app(QueueSelector::class)->sendNext($this->party);
        expect(scheduledSent())->toBe(['SCHED']);
    });

    it('beats a higher-score unscheduled request once due', function () {
        Carbon::setTestNow('2030-01-02 00:00:00');
        scheduledRequest($this->party, 'HIGH', ['score' => 50]);
        scheduledRequest($this->party, 'SCHED', ['score' => 0, 'scheduled_for' => now()->subMinute(), 'not_before' => now()->subMinute()]);

        app(QueueSelector::class)->sendNext($this->party);

        expect(scheduledSent())->toBe(['SCHED']);
    });

    it('plays the earliest due request first', function () {
        Carbon::setTestNow('2030-01-02 00:00:00');
        scheduledRequest($this->party, 'LATER', ['score' => 9, 'scheduled_for' => now()->subMinute(), 'not_before' => now()->subMinute()]);
        scheduledRequest($this->party, 'EARLIER', ['score' => 0, 'scheduled_for' => now()->subHour(), 'not_before' => now()->subHour()]);

        app(QueueSelector::class)->sendNext($this->party);

        expect(scheduledSent())->toBe(['EARLIER']);
    });
});

describe('cancelling', function () {
    it('lets a manager cancel and refuses a member', function () {
        $request = scheduledRequest($this->party, 'SCHED', ['scheduled_for' => now()->addHour(), 'not_before' => now()->addHour()]);
        $url = route('api.v1.parties.upcomingsongs.destroy', [$this->party, $request]);

        Sanctum::actingAs(scheduledMember($this->party, 'user'));
        $this->deleteJson($url)->assertForbidden();

        Sanctum::actingAs(scheduledMember($this->party, 'owner'));
        $this->deleteJson($url)->assertNoContent();
        expect(UpcomingSong::count())->toBe(0);
    });
});

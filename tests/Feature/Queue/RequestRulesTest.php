<?php

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Queue\RequestStatus;
use App\Events\Party\RequestRejectedEvent;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function ruleTrack(string $id, int $seconds = 180, bool $explicit = false, ?string $isrc = null): TrackData
{
    return new TrackData('fake', $id, "Song {$id}", [new ArtistData('a', 'Artist')], new AlbumData('al', 'Album'), $seconds * 1000, $explicit, $isrc);
}

beforeEach(function () {
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider([
        ruleTrack('t1', isrc: 'ISRC1'),
        ruleTrack('t1-remaster', isrc: 'ISRC1'),
        ruleTrack('t2', isrc: 'ISRC2'),
        ruleTrack('short', 30),
        ruleTrack('long', 600),
        ruleTrack('dirty', explicit: true),
    ]));
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->member = PartyMember::factory()->for($this->party)->create();
});

function ruleRequest(PartyMember $member, string $trackId)
{
    Sanctum::actingAs($member->user);

    return test()->postJson('/api/v1/parties/ABCD/requests', ['provider_track_id' => $trackId]);
}

function activeRequest(Party $party, PartyMember $member, array $attributes = []): TrackRequest
{
    return TrackRequest::factory()->create(['party_id' => $party->id, 'party_member_id' => $member->id, ...$attributes]);
}

it('refuses a track outside the length limits citing the limit', function (array $settings, string $trackId, string $message) {
    $this->party->forceFill($settings)->save();

    ruleRequest($this->member, $trackId)->assertUnprocessable()->assertJsonPath('message', $message);

    expect(TrackRequest::query()->count())->toBe(0);
})->with([
    'too short' => [['min_song_length' => 60], 'short', "That track is shorter than this party's minimum length of 60 seconds."],
    'too long' => [['max_song_length' => 300], 'long', "That track is longer than this party's maximum length of 300 seconds."],
]);

it('accepts tracks within or without length limits', function (array $settings, string $trackId) {
    $this->party->forceFill($settings)->save();

    ruleRequest($this->member, $trackId)->assertCreated();
})->with([
    'no limits' => [[], 'long'],
    'zero means no limit' => [['min_song_length' => 0, 'max_song_length' => 0], 'short'],
    'exactly at minimum' => [['min_song_length' => 30], 'short'],
    'exactly at maximum' => [['max_song_length' => 600], 'long'],
]);

it('applies the explicit filter', function (bool $allowed, int $status) {
    $this->party->forceFill(['explicit' => $allowed])->save();

    ruleRequest($this->member, 'dirty')->assertStatus($status);
})->with([[false, 422], [true, 201]]);

it('enforces the per-member limit with role exemptions', function (string $role, int $status) {
    $this->party->forceFill(['max_requests' => 1])->save();
    $member = PartyMember::factory()->for($this->party)->create(['role' => $role]);
    activeRequest($this->party, $member);

    ruleRequest($member, 't2')->assertStatus($status);
})->with([
    'guest' => ['guest', 422],
    'moderator is not exempt' => ['moderator', 422],
    'vip exempt' => ['vip', 201],
    'host exempt' => ['host', 201],
]);

it('counts only pending, queued and up next requests toward the limit', function (RequestStatus $status, int $expected) {
    $this->party->forceFill(['max_requests' => 1])->save();
    activeRequest($this->party, $this->member, ['status' => $status]);

    ruleRequest($this->member, 't2')->assertStatus($expected);
})->with([
    [RequestStatus::Pending, 422],
    [RequestStatus::Queued, 422],
    [RequestStatus::UpNext, 422],
    [RequestStatus::Played, 201],
    [RequestStatus::Rejected, 201],
    [RequestStatus::Removed, 201],
]);

it('has no per-member limit when unset', function () {
    activeRequest($this->party, $this->member);

    ruleRequest($this->member, 't2')->assertCreated();
});

it('applies the no-repeat interval by provider track id or isrc', function (string $trackId, int $minutesAgo, int $status) {
    $this->party->forceFill(['no_repeat_interval' => 3600])->save();
    $played = activeRequest($this->party, $this->member, ['provider_track_id' => 't1', 'isrc' => 'ISRC1', 'status' => RequestStatus::Played]);
    $played->forceFill(['updated_at' => now()->subMinutes($minutesAgo)])->saveQuietly();

    $response = ruleRequest($this->member, $trackId)->assertStatus($status);

    if ($status === 409) {
        $response->assertJsonPath('message', 'That track was last played '.now()->subMinutes($minutesAgo)->diffForHumans().' and cannot be requested again yet.');
    }
})->with([
    'within interval' => ['t1', 10, 409],
    'same recording other release' => ['t1-remaster', 10, 409],
    'outside interval' => ['t1', 90, 201],
    'different track' => ['t2', 10, 201],
]);

it('ignores plays when no repeat interval is set', function () {
    activeRequest($this->party, $this->member, ['provider_track_id' => 't1', 'status' => RequestStatus::Played]);

    ruleRequest($this->member, 't1')->assertCreated();
});

it('refuses the track currently Up Next', function (string $trackId) {
    activeRequest($this->party, PartyMember::factory()->for($this->party)->create(), ['provider_track_id' => 't1', 'isrc' => 'ISRC1', 'status' => RequestStatus::UpNext]);

    ruleRequest($this->member, $trackId)->assertConflict()->assertJsonPath('message', 'That track is already Up Next.');

    expect(TrackRequest::query()->count())->toBe(1);
})->with(['same track' => 't1', 'same recording' => 't1-remaster']);

it('treats the same isrc on a different release as a duplicate upvote', function () {
    $first = ruleRequest($this->member, 't1')->assertCreated();
    expect(TrackRequest::query()->sole()->isrc)->toBe('ISRC1');
    $other = PartyMember::factory()->for($this->party)->create();

    ruleRequest($other, 't1-remaster')->assertOk()->assertJsonPath('meta.duplicate', true)->assertJsonPath('data.score', 2);

    expect(TrackRequest::query()->count())->toBe(1);
});

it('does not dedupe tracks without an isrc', function () {
    $this->app->instance(FakeMusicProvider::class, new FakeMusicProvider([ruleTrack('x'), ruleTrack('y')]));
    ruleRequest($this->member, 'x')->assertCreated();
    ruleRequest(PartyMember::factory()->for($this->party)->create(), 'y')->assertCreated();

    expect(TrackRequest::query()->count())->toBe(2);
});

it('lets a duplicate upvote bypass the per-member limit and length rules because it adds no request', function () {
    ruleRequest($this->member, 't1')->assertCreated();
    $other = PartyMember::factory()->for($this->party)->create();
    activeRequest($this->party, $other);
    $this->party->forceFill(['max_requests' => 1, 'max_song_length' => 10])->save();

    ruleRequest($other, 't1')->assertOk()->assertJsonPath('meta.duplicate', true);
});

it('refuses at the first failing rule in spec order', function () {
    $this->party->forceFill(['max_requests' => 1, 'max_song_length' => 10, 'explicit' => false])->save();
    activeRequest($this->party, $this->member);

    ruleRequest($this->member, 'dirty')->assertUnprocessable()->assertJsonPath('message', 'You already have 1 active requests, which is the limit for this party.');
});

it('refuses with requests disabled before anything else', function () {
    $this->party->forceFill(['allow_requests' => false])->save();

    ruleRequest($this->member, 't1')->assertUnprocessable();
});

it('notifies the requester privately when a rule refuses', function () {
    Event::fake([RequestRejectedEvent::class]);
    $this->party->forceFill(['max_song_length' => 10])->save();

    ruleRequest($this->member, 'long')->assertUnprocessable();

    Event::assertDispatched(RequestRejectedEvent::class, fn (RequestRejectedEvent $event) => $event->broadcastOn()[0]->name === "private-party.ABCD.member.{$this->member->id}"
        && $event->broadcastWith() === ['provider_track_id' => 'long', 'reason' => "That track is longer than this party's maximum length of 10 seconds."]);
});

it('does not broadcast a rejection for non-rule refusals or accepted requests', function (string $trackId) {
    Event::fake([RequestRejectedEvent::class]);
    $banned = PartyMember::factory()->for($this->party)->banned()->create();

    ruleRequest($banned, $trackId)->assertForbidden();
    ruleRequest($this->member, 't2')->assertCreated();

    Event::assertNotDispatched(RequestRejectedEvent::class);
})->with(['t1']);

function authoriseMemberChannel(?User $user, string $channel)
{
    $test = $user === null ? test() : test()->actingAs($user);

    return $test->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);
}

describe('per-member channel', function () {
    beforeEach(function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::purge();
        require base_path('routes/channels.php');
    });

    it('admits only the member themselves', function () {
        authoriseMemberChannel($this->member->user, "private-party.ABCD.member.{$this->member->id}")->assertOk();
    });

    it('refuses other members, banned members, non-members and unknown codes', function () {
        $other = PartyMember::factory()->for($this->party)->create();
        $banned = PartyMember::factory()->for($this->party)->banned()->create();
        $channel = "private-party.ABCD.member.{$this->member->id}";

        authoriseMemberChannel($other->user, $channel)->assertForbidden();
        authoriseMemberChannel($banned->user, "private-party.ABCD.member.{$banned->id}")->assertForbidden();
        authoriseMemberChannel(User::factory()->create(), $channel)->assertForbidden();
        authoriseMemberChannel($this->member->user, "private-party.ZZZZ.member.{$this->member->id}")->assertForbidden();
    });

    it('refuses unauthenticated callers', function () {
        authoriseMemberChannel(null, "private-party.ABCD.member.{$this->member->id}")->assertForbidden();
    });
});

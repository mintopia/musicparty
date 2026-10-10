<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');
    $this->party = Party::factory()->create(['code' => 'ABCD']);
});

function authorisePresence(?User $user, string $code = 'ABCD')
{
    $test = $user === null ? test() : test()->actingAs($user);

    return $test->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "presence-party.{$code}.members",
    ]);
}

it('admits a member and exposes only nickname and avatar', function () {
    $member = PartyMember::factory()->for($this->party)->create();

    $response = authorisePresence($member->user)->assertOk();
    $data = json_decode($response->json('channel_data'), true);

    expect((int) $data['user_id'])->toBe($member->user_id)
        ->and($data['user_info'])->toBe(['nickname' => $member->user->nickname, 'avatar' => $member->user->avatarUrl()]);
});

it('admits a moderator and the host', function (string $state) {
    $member = PartyMember::factory()->for($this->party)->{$state}()->create();

    authorisePresence($member->user)->assertOk();
})->with(['host', 'moderator', 'vip']);

it('refuses banned members, non-members and unknown codes identically', function () {
    $banned = PartyMember::factory()->for($this->party)->banned()->create();
    $member = PartyMember::factory()->for($this->party)->create();

    $refusals = [
        authorisePresence($banned->user),
        authorisePresence(User::factory()->create()),
        authorisePresence($member->user, 'ZZZZ'),
    ];

    foreach ($refusals as $response) {
        expect($response->status())->toBe(403);
    }

    expect($refusals[1]->json('message'))->toBe($refusals[2]->json('message'))
        ->and($refusals[0]->json('message'))->toBe($refusals[2]->json('message'));
});

it('lets a code be matched case-insensitively for a member', function () {
    $member = PartyMember::factory()->for($this->party)->create();

    authorisePresence($member->user, 'abcd')->assertOk();
});

it('admits a previously banned member again after an unban', function () {
    $member = PartyMember::factory()->for($this->party)->banned()->create();

    authorisePresence($member->user)->assertForbidden();
    $member->forceFill(['banned' => false])->save();
    authorisePresence($member->user)->assertOk();
});

it('refuses unauthenticated callers', function () {
    authorisePresence(null)->assertForbidden();
});

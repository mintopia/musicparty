<?php

use App\Domain\Party\PartyRole;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\User;
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

function authoriseModerators(?User $user, string $code = 'ABCD')
{
    $test = $user === null ? test() : test()->actingAs($user);

    return $test->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-party.{$code}.moderators"]);
}

it('admits the host and moderators', function (string $state) {
    authoriseModerators(PartyMember::factory()->for($this->party)->{$state}()->create()->user)->assertOk();
})->with(['host', 'moderator']);

it('refuses guests, vips, banned moderators, non-members and unknown codes', function () {
    $guest = PartyMember::factory()->for($this->party)->create();
    $vip = PartyMember::factory()->for($this->party)->vip()->create();
    $banned = PartyMember::factory()->for($this->party)->moderator()->banned()->create();
    $moderator = PartyMember::factory()->for($this->party)->moderator()->create();

    authoriseModerators($guest->user)->assertForbidden();
    authoriseModerators($vip->user)->assertForbidden();
    authoriseModerators($banned->user)->assertForbidden();
    authoriseModerators(User::factory()->create())->assertForbidden();
    authoriseModerators($moderator->user, 'ZZZZ')->assertForbidden();
});

it('refuses unauthenticated callers', function () {
    authoriseModerators(null)->assertForbidden();
});

it('refuses a moderator on the next subscription after demotion or ban', function (string $change) {
    $moderator = PartyMember::factory()->for($this->party)->moderator()->create();

    authoriseModerators($moderator->user)->assertOk();
    $moderator->forceFill($change === 'demoted' ? ['role' => PartyRole::Guest] : ['banned' => true])->save();
    authoriseModerators($moderator->user)->assertForbidden();
})->with(['demoted', 'banned']);

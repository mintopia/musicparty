<?php

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
    require base_path('routes/channels.php');
    $this->host = User::factory()->create();
    $this->party = Party::factory()->create(['user_id' => $this->host->id, 'music_provider' => 'fake', 'player_kind' => 'fake']);
    $this->plain = $this->party->createToken('Stage', ['player:connect'])->plainTextToken;
});

function authorisePlayerChannel(object $test, string $code): TestResponse
{
    return $test->postJson('/broadcasting/auth', ['channel_name' => 'private-player.'.$code, 'socket_id' => '1234.5678']);
}

it('authorises the matching party player token', function () {
    $this->withToken($this->plain);

    authorisePlayerChannel($this, $this->party->code)->assertOk()->assertJsonStructure(['auth']);
});

it('refuses a token for another party', function () {
    $other = Party::factory()->create();
    $this->withToken($other->createToken('Stage', ['player:connect'])->plainTextToken);

    authorisePlayerChannel($this, $this->party->code)->assertForbidden();
});

it('refuses a token without the player ability', function () {
    $this->withToken($this->party->createToken('Weak', ['other:thing'])->plainTextToken);

    authorisePlayerChannel($this, $this->party->code)->assertForbidden();
});

it('refuses a revoked token', function () {
    $this->party->tokens()->delete();
    $this->withToken($this->plain);

    authorisePlayerChannel($this, $this->party->code)->assertForbidden();
});

it('refuses the host user without a player token', function () {
    Sanctum::actingAs($this->host, ['*']);

    authorisePlayerChannel($this, $this->party->code)->assertForbidden();
});

it('refuses an unknown party code the same as a forbidden one', function () {
    $this->withToken($this->plain);

    authorisePlayerChannel($this, 'NOSUCH')->assertForbidden();
});

it('refuses unauthenticated requests', function () {
    authorisePlayerChannel($this, $this->party->code)->assertForbidden();
});

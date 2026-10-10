<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Playback\Jobs\PollPlayback;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    app()->instance(FakeMusicProvider::class, new FakeMusicProvider);
    $this->party = Party::factory()->live()->create(['player_kind' => 'polling']);
    $this->token = $this->party->createToken('Stage', ['player:connect'])->plainTextToken;
});

function pollWebhook(Party $party, ?string $token): TestResponse
{
    $request = $token === null ? test() : test()->withToken($token);

    return $request->postJson("/api/v1/parties/{$party->code}/player/poll");
}

it('dispatches an immediate one-off poll', function () {
    pollWebhook($this->party, $this->token)->assertAccepted();

    Queue::assertPushed(PollPlayback::class, fn (PollPlayback $job) => $job->partyCode === $this->party->code && $job->reschedule === false);
    Queue::assertPushed(PollPlayback::class, 1);
});

it('rejects a request without a token', function () {
    pollWebhook($this->party, null)->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('rejects a revoked token', function () {
    $this->party->tokens()->delete();

    pollWebhook($this->party, $this->token)->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('rejects a token for another party', function () {
    $other = Party::factory()->live()->create(['player_kind' => 'polling']);

    pollWebhook($other, $this->token)->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects a token without the player ability', function () {
    $token = $this->party->createToken('Other', ['something:else'])->plainTextToken;

    pollWebhook($this->party, $token)->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects a user principal', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/parties/{$this->party->code}/player/poll")->assertForbidden();

    Queue::assertNothingPushed();
});

it('refuses when the party is not live', function () {
    $party = Party::factory()->create(['player_kind' => 'polling']);
    $token = $party->createToken('Stage', ['player:connect'])->plainTextToken;

    pollWebhook($party, $token)->assertUnprocessable();

    Queue::assertNothingPushed();
});

it('refuses when the player is not a polling player', function () {
    $party = Party::factory()->live()->create(['player_kind' => 'fake']);
    $token = $party->createToken('Stage', ['player:connect'])->plainTextToken;

    pollWebhook($party, $token)->assertUnprocessable();

    Queue::assertNothingPushed();
});

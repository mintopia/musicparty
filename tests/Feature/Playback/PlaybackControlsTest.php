<?php

use App\Domain\Party\PartyRole;
use App\Domain\Playback\Actions\ControlPlayback;
use App\Domain\Playback\Control;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\PlaybackCoordinator;
use App\Domain\Playback\Testing\FakePlayer;
use App\Domain\Queue\RequestStatus;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\TrackRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->player = useFakePlayer($this->party);
});

function playbackUser(Party $party, ?PartyRole $role): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        PartyMember::factory()->for($party)->for($user)->create(['role' => $role]);
    }

    return $user;
}

/**
 * @return array<string, array{0: string, 1: array<string, int>}>
 */
function playbackControls(): array
{
    return [
        'play' => ['play', []],
        'pause' => ['pause', []],
        'skip' => ['skip', []],
        'seek' => ['seek', ['position_ms' => 42000]],
        'volume' => ['volume', ['level' => 65]],
    ];
}

function webControl(string $control, array $payload = [], string $code = 'ABCD')
{
    return test()->post("/parties/{$code}/playback/{$control}", $payload);
}

function apiControl(string $control, array $payload = [], string $code = 'ABCD')
{
    return test()->postJson("/api/v1/parties/{$code}/playback/{$control}", $payload);
}

function lastCommand(FakePlayer $player): array
{
    $command = array_last($player->commands());

    return [$command->type, $command->value];
}

it('lets the party owner control playback on web and API', function (string $control, array $payload) {
    $owner = User::factory()->create();
    $this->party->forceFill(['user_id' => $owner->id])->save();
    $value = $payload === [] ? null : array_values($payload)[0];

    $this->actingAs($owner);
    webControl($control, $payload)->assertRedirect();
    expect(lastCommand($this->player))->toBe([$control, $value]);

    Sanctum::actingAs($owner);
    apiControl($control, $payload)->assertOk()
        ->assertJsonPath('data.control', $control)
        ->assertJsonPath('data.value', $value);
    expect($this->player->commands())->toHaveCount(2);
})->with(playbackControls());

it('lets a Host member control playback and sends the value to the Player', function (string $control, array $payload) {
    $host = playbackUser($this->party, PartyRole::Host);
    $value = $payload === [] ? null : array_values($payload)[0];

    Sanctum::actingAs($host);
    apiControl($control, $payload)->assertOk();

    expect(lastCommand($this->player))->toBe([$control, $value]);
})->with(playbackControls());

it('refuses everyone who is not a Host with 403 and sends no command', function (?PartyRole $role, string $control, array $payload) {
    $user = playbackUser($this->party, $role);

    $this->actingAs($user);
    webControl($control, $payload)->assertForbidden();

    Sanctum::actingAs($user);
    apiControl($control, $payload)->assertForbidden();

    expect($this->player->commands())->toBe([]);
})->with([
    'moderator' => PartyRole::Moderator,
    'vip' => PartyRole::Vip,
    'guest' => PartyRole::Guest,
    'non-member' => null,
])->with(playbackControls());

it('rejects unauthenticated callers', function (string $control, array $payload) {
    webControl($control, $payload)->assertRedirect();
    apiControl($control, $payload)->assertUnauthorized();

    expect($this->player->commands())->toBe([]);
})->with(playbackControls());

it('refuses an unsupported control with a clear error', function () {
    $party = Party::factory()->live()->create(['code' => 'EFGH']);
    $player = new FakePlayer(supportedControls: [Control::Play]);
    app(PartyPlayers::class)->register($party, $player);
    $owner = User::factory()->create();
    $party->forceFill(['user_id' => $owner->id])->save();

    $this->actingAs($owner);
    webControl('volume', ['level' => 10], 'EFGH')->assertSessionHasErrors(['playback' => 'This Player does not support volume.']);

    Sanctum::actingAs($owner);
    apiControl('volume', ['level' => 10], 'EFGH')->assertUnprocessable()
        ->assertJsonPath('message', 'This Player does not support volume.');

    expect($player->commands())->toBe([]);
});

it('refuses a control when the Player is disconnected', function () {
    $owner = User::factory()->create();
    $this->party->forceFill(['user_id' => $owner->id])->save();
    $this->player->disconnect();

    $this->actingAs($owner);
    webControl('skip')->assertSessionHasErrors(['playback' => 'The Player is disconnected, so playback cannot be controlled.']);

    Sanctum::actingAs($owner);
    apiControl('skip')->assertStatus(409)
        ->assertJsonPath('message', 'The Player is disconnected, so playback cannot be controlled.');
});

it('refuses a control when no Player is paired', function () {
    $party = Party::factory()->live()->create(['code' => 'WXYZ', 'player_kind' => null]);
    $owner = User::factory()->create();
    $party->forceFill(['user_id' => $owner->id])->save();

    $this->actingAs($owner);
    webControl('play', [], 'WXYZ')->assertSessionHasErrors('playback');

    Sanctum::actingAs($owner);
    apiControl('play', [], 'WXYZ')->assertStatus(409);
});

it('validates seek and volume on web and API', function (string $control, array $payload, string $field) {
    $owner = User::factory()->create();
    $this->party->forceFill(['user_id' => $owner->id])->save();

    $this->actingAs($owner);
    webControl($control, $payload)->assertSessionHasErrors($field);

    Sanctum::actingAs($owner);
    apiControl($control, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($this->player->commands())->toBe([]);
})->with([
    'seek negative' => ['seek', ['position_ms' => -1], 'position_ms'],
    'seek missing' => ['seek', [], 'position_ms'],
    'seek not integer' => ['seek', ['position_ms' => 'soon'], 'position_ms'],
    'volume above 100' => ['volume', ['level' => 101], 'level'],
    'volume below 0' => ['volume', ['level' => -1], 'level'],
    'volume missing' => ['volume', [], 'level'],
]);

it('accepts the boundary values for seek and volume', function () {
    $owner = User::factory()->create();
    $this->party->forceFill(['user_id' => $owner->id])->save();
    Sanctum::actingAs($owner);

    apiControl('seek', ['position_ms' => 0])->assertOk();
    apiControl('volume', ['level' => 0])->assertOk();
    apiControl('volume', ['level' => 100])->assertOk();

    expect(array_map(fn ($c) => [$c->type, $c->value], $this->player->commands()))
        ->toBe([['seek', 0], ['volume', 0], ['volume', 100]]);
});

it('returns not found for an unknown control', function () {
    $owner = User::factory()->create();
    $this->party->forceFill(['user_id' => $owner->id])->save();
    Sanctum::actingAs($owner);

    apiControl('rewind')->assertNotFound();
});

it('exposes the control ability only to those who can manage the party', function () {
    expect(playbackUser($this->party, PartyRole::Host)->can('control', $this->party))->toBeTrue()
        ->and(playbackUser($this->party, PartyRole::Moderator)->can('control', $this->party))->toBeFalse();
});

it('retries a failed enqueue at once after the Host plays or skips', function (Control $control) {
    $request = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::UpNext]);
    $coordinator = app(PlaybackCoordinator::class);
    $this->player->disconnect();

    $coordinator->tick($this->party);
    $this->player->reconnect();
    $coordinator->tick($this->party);

    expect($request->fresh()->enqueued_at)->toBeNull();

    app(ControlPlayback::class)($this->party, $control);
    $coordinator->tick($this->party);

    expect($request->fresh()->enqueued_at)->not->toBeNull();
})->with([Control::Play, Control::Skip]);

it('keeps the enqueue backoff when the Host pauses', function () {
    $request = TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::UpNext]);
    $coordinator = app(PlaybackCoordinator::class);
    $this->player->disconnect();

    $coordinator->tick($this->party);
    $this->player->reconnect();
    app(ControlPlayback::class)($this->party, Control::Pause);
    $coordinator->tick($this->party);

    expect($request->fresh()->enqueued_at)->toBeNull();
});

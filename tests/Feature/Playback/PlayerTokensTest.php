<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\PartyRole;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Testing\FakePlayer;
use App\Models\Party;
use App\Models\PartyLogEntry;
use App\Models\PartyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->host = User::factory()->create();
    $this->party = Party::factory()->create(['user_id' => $this->host->id, 'music_provider' => 'fake', 'player_kind' => 'fake']);
});

function registerElsewherePlayer(): void
{
    config(['musicparty.players.elsewhere' => ['label' => 'Elsewhere player', 'class' => 'test.elsewhere']]);
    app()->bind('test.elsewhere', fn (): FakePlayer => new FakePlayer(kind: 'elsewhere', compatibleProviders: ['spotify']));
}

function playerPartyMember(Party $party, PartyRole $role): User
{
    $user = User::factory()->create();
    PartyMember::factory()->for($party)->for($user)->create(['role' => $role]);

    return $user;
}

function playerLog(Party $party, string $action): ?PartyLogEntry
{
    return PartyLogEntry::query()->where('party_id', $party->id)->where('action', $action)->latest('id')->first();
}

function issuePlayerToken(object $test, Party $party, string $name = 'Main stage'): string
{
    return $test->postJson(route('api.v1.parties.player-tokens.store', $party), ['name' => $name])->assertCreated()->json('data.token');
}

it('changes the player pairing and records the change', function () {
    registerElsewherePlayer();
    config(['musicparty.music_providers.spotify' => ['label' => 'Spotify', 'class' => 'test.spotify']]);
    app()->bind('test.spotify', fn () => new FakeMusicProvider(id: 'spotify'));
    Sanctum::actingAs($this->host);

    $this->putJson(route('api.v1.parties.player.update', $this->party), ['player_kind' => 'elsewhere', 'music_provider' => 'spotify'])
        ->assertOk()
        ->assertExactJson(['data' => ['player_kind' => 'elsewhere', 'music_provider' => 'spotify']]);

    $party = $this->party->fresh();
    $entry = playerLog($party, 'player.changed');

    expect($party->player_kind)->toBe('elsewhere')
        ->and($party->music_provider)->toBe('spotify')
        ->and($entry->user_id)->toBe($this->host->id)
        ->and($entry->details)->toBe(['from' => ['player_kind' => 'fake', 'music_provider' => 'fake'], 'to' => ['player_kind' => 'elsewhere', 'music_provider' => 'spotify']]);
});

it('keeps the music provider when none is given', function () {
    Sanctum::actingAs($this->host);

    $this->putJson(route('api.v1.parties.player.update', $this->party), ['player_kind' => 'fake'])
        ->assertOk()
        ->assertJsonPath('data.music_provider', 'fake');
});

it('does not log or change anything when the pairing is unchanged', function () {
    Sanctum::actingAs($this->host);

    $this->putJson(route('api.v1.parties.player.update', $this->party), ['player_kind' => 'fake', 'music_provider' => 'fake'])->assertOk();

    expect(playerLog($this->party, 'player.changed'))->toBeNull();
});

it('forgets the cached player when the pairing changes', function () {
    registerElsewherePlayer();
    config(['musicparty.music_providers.spotify' => ['label' => 'Spotify', 'class' => 'test.spotify']]);
    app()->bind('test.spotify', fn () => new FakeMusicProvider(id: 'spotify'));
    $players = app(PartyPlayers::class);
    $before = $players->for($this->party);
    Sanctum::actingAs($this->host);

    $this->putJson(route('api.v1.parties.player.update', $this->party), ['player_kind' => 'elsewhere', 'music_provider' => 'spotify'])->assertOk();

    expect($players->for($this->party->fresh())->kind())->toBe('elsewhere')->and($players->for($this->party->fresh()))->not->toBe($before);
});

it('refuses an incompatible pairing naming the compatible options', function () {
    registerElsewherePlayer();
    Sanctum::actingAs($this->host);

    $response = $this->putJson(route('api.v1.parties.player.update', $this->party), ['player_kind' => 'elsewhere', 'music_provider' => 'fake'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('player_kind');

    expect($response->json('errors.player_kind.0'))->toContain('Compatible: spotify')
        ->and($this->party->fresh()->player_kind)->toBe('fake')
        ->and(playerLog($this->party, 'player.changed'))->toBeNull();
});

it('refuses unknown player kinds and providers', function (array $payload, string $field) {
    Sanctum::actingAs($this->host);

    $this->putJson(route('api.v1.parties.player.update', $this->party), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'unknown kind' => [['player_kind' => 'nope'], 'player_kind'],
    'unknown provider' => [['player_kind' => 'fake', 'music_provider' => 'nope'], 'music_provider'],
    'missing kind' => [[], 'player_kind'],
]);

it('refuses everyone but the host', function (string $who) {
    $principal = match ($who) {
        'moderator' => playerPartyMember($this->party, PartyRole::Moderator),
        'guest' => playerPartyMember($this->party, PartyRole::Guest),
        'other' => User::factory()->create(),
    };
    Sanctum::actingAs($principal);

    $this->putJson(route('api.v1.parties.player.update', $this->party), ['player_kind' => 'fake'])->assertForbidden();
    $this->getJson(route('api.v1.parties.player-tokens.index', $this->party))->assertForbidden();
    $this->postJson(route('api.v1.parties.player-tokens.store', $this->party), ['name' => 'x'])->assertForbidden();
    $this->deleteJson(route('api.v1.parties.player-tokens.destroy', [$this->party, 1]))->assertForbidden();

    expect($this->party->tokens()->count())->toBe(0);
})->with(['moderator', 'guest', 'other']);

it('requires authentication', function () {
    $this->putJson(route('api.v1.parties.player.update', $this->party), ['player_kind' => 'fake'])->assertUnauthorized();
    $this->getJson(route('api.v1.parties.player-tokens.index', $this->party))->assertUnauthorized();
    $this->postJson(route('api.v1.parties.player-tokens.store', $this->party), ['name' => 'x'])->assertUnauthorized();
    $this->deleteJson(route('api.v1.parties.player-tokens.destroy', [$this->party, 1]))->assertUnauthorized();
});

it('refuses a player token principal on management routes', function () {
    $this->withToken($this->party->createToken('Stage', ['player:connect'])->plainTextToken);

    $this->getJson(route('api.v1.parties.player-tokens.index', $this->party))->assertForbidden();
    $this->putJson(route('api.v1.parties.player.update', $this->party), ['player_kind' => 'fake'])->assertForbidden();
    $this->postJson(route('api.v1.parties.player-tokens.store', $this->party), ['name' => 'x'])->assertForbidden();
    $this->deleteJson(route('api.v1.parties.player-tokens.destroy', [$this->party, 1]))->assertForbidden();
});

it('issues a token once and logs it without the secret', function () {
    Sanctum::actingAs($this->host);

    $response = $this->postJson(route('api.v1.parties.player-tokens.store', $this->party), ['name' => 'Main stage'])->assertCreated();
    $plain = $response->json('data.token');
    $stored = PersonalAccessToken::query()->firstOrFail();
    $entry = playerLog($this->party, 'player_token.issued');

    expect($plain)->toContain('|')
        ->and($response->json('data.name'))->toBe('Main stage')
        ->and($response->json('data.abilities'))->toBe(['player:connect'])
        ->and($stored->token)->not->toBe($plain)
        ->and($stored->tokenable_id)->toBe($this->party->id)
        ->and($entry->subject)->toBe('Main stage')
        ->and($entry->details)->toBe(['token_id' => $stored->id])
        ->and(json_encode($entry->toArray()))->not->toContain(explode('|', $plain)[1]);
});

it('lists token metadata without ever exposing the secret', function () {
    Sanctum::actingAs($this->host);
    $plain = issuePlayerToken($this, $this->party);

    $response = $this->getJson(route('api.v1.parties.player-tokens.index', $this->party))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'name', 'abilities', 'created_at', 'last_used_at'])
        ->and($response->getContent())->not->toContain(explode('|', $plain)[1]);
});

it('lists only this party tokens', function () {
    $other = Party::factory()->create(['user_id' => $this->host->id]);
    $other->createToken('Elsewhere', ['player:connect']);
    Sanctum::actingAs($this->host);

    $this->getJson(route('api.v1.parties.player-tokens.index', $this->party))->assertOk()->assertJsonCount(0, 'data');
});

it('validates the token name', function (array $payload) {
    Sanctum::actingAs($this->host);

    $this->postJson(route('api.v1.parties.player-tokens.store', $this->party), $payload)->assertUnprocessable()->assertJsonValidationErrors('name');
})->with([[[]], [['name' => str_repeat('a', 65)]], [['name' => ['x']]]]);

it('revokes a token and logs it', function () {
    Sanctum::actingAs($this->host);
    issuePlayerToken($this, $this->party);
    $token = $this->party->tokens()->firstOrFail();

    $this->deleteJson(route('api.v1.parties.player-tokens.destroy', [$this->party, $token->id]))->assertNoContent();

    $entry = playerLog($this->party, 'player_token.revoked');
    expect($this->party->tokens()->count())->toBe(0)
        ->and($entry->subject)->toBe('Main stage')
        ->and($entry->details)->toBe(['token_id' => $token->id]);
});

it('returns not found revoking a token of another party or a missing one', function () {
    $other = Party::factory()->create(['user_id' => $this->host->id]);
    $foreign = $other->createToken('Elsewhere', ['player:connect'])->accessToken;
    Sanctum::actingAs($this->host);

    $this->deleteJson(route('api.v1.parties.player-tokens.destroy', [$this->party, $foreign->id]))->assertNotFound();
    $this->deleteJson(route('api.v1.parties.player-tokens.destroy', [$this->party, 9999]))->assertNotFound();

    expect($other->tokens()->count())->toBe(1);
});

describe('player token middleware', function () {
    beforeEach(function () {
        Route::middleware(['api', 'auth:sanctum', 'player.token'])->get('api/v1/__player/{party}', fn (Party $party) => response()->json(['party' => $party->code]));
        $this->plain = $this->party->createToken('Stage', ['player:connect'])->plainTextToken;
    });

    it('accepts a token for its own party', function () {
        $this->withToken($this->plain)->getJson("/api/v1/__player/{$this->party->code}")->assertOk()->assertJson(['party' => $this->party->code]);
    });

    it('rejects a token for another party', function () {
        $other = Party::factory()->create();

        $this->withToken($this->plain)->getJson("/api/v1/__player/{$other->code}")->assertForbidden();
    });

    it('rejects a token without the player ability', function () {
        $plain = $this->party->createToken('Weak', ['other:thing'])->plainTextToken;

        $this->withToken($plain)->getJson("/api/v1/__player/{$this->party->code}")->assertForbidden();
    });

    it('rejects a user principal', function () {
        Sanctum::actingAs($this->host, ['*']);

        $this->getJson("/api/v1/__player/{$this->party->code}")->assertForbidden();
    });

    it('rejects missing credentials', function () {
        $this->getJson("/api/v1/__player/{$this->party->code}")->assertUnauthorized();
    });

    it('rejects a revoked token', function () {
        $this->party->tokens()->delete();

        $this->withToken($this->plain)->getJson("/api/v1/__player/{$this->party->code}")->assertUnauthorized();
    });
});

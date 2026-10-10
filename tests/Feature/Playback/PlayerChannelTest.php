<?php

use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Http\Middleware\VerifyCsrfToken;
use App\Support\Realtime\PlayerConnections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
    require base_path('routes/channels.php');
    $this->host = User::factory()->create();
    $this->party = Party::factory()->create(['user_id' => $this->host->id, 'music_provider' => 'fake', 'player_kind' => 'fake']);
    $this->plain = $this->party->createToken('Stage', ['player:connect'])->plainTextToken;
});

/**
 * @return TestResponse<Response>
 */
function authorisePlayerChannel(object $test, string $code): TestResponse
{
    return $test->postJson('/broadcasting/auth', ['channel_name' => 'private-player.'.$code, 'socket_id' => '1234.5678']);
}

it('authorises the matching party player token', function () {
    $this->withToken($this->plain);

    authorisePlayerChannel($this, $this->party->code)->assertOk()->assertJsonStructure(['auth']);
});

it('records the socket against the token when authorising', function () {
    $this->withToken($this->plain);

    authorisePlayerChannel($this, $this->party->code)->assertOk();

    expect(app(PlayerConnections::class)->accepts('1234.5678', $this->party->code))->toBeTrue()
        ->and(app(PlayerConnections::class)->accepts('9999.0000', $this->party->code))->toBeFalse();
});

it('does not record a socket for a refused authorisation', function () {
    $this->withToken($this->party->createToken('Weak', ['other:thing'])->plainTextToken);

    authorisePlayerChannel($this, $this->party->code)->assertForbidden();

    expect(app(PlayerConnections::class)->accepts('1234.5678', $this->party->code))->toBeFalse();
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

function enforceCsrfInTests(): void
{
    app()->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
}

it('enforces CSRF on a session request carrying a bogus bearer header', function () {
    enforceCsrfInTests();
    $this->actingAs($this->host)->withToken('bogus');

    authorisePlayerChannel($this, $this->party->code)->assertStatus(419);
});

it('skips CSRF for a valid player token', function () {
    enforceCsrfInTests();
    $this->withToken($this->plain);

    authorisePlayerChannel($this, $this->party->code)->assertOk();
});

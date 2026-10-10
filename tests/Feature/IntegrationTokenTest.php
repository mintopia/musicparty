<?php

use App\Domain\Admin\Actions\IssueIntegrationToken;
use App\Domain\Admin\Actions\RevokeIntegrationToken;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Admin\Models\Integration;
use App\Domain\Admin\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

function tokenAdmin(): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['code' => 'admin'], ['name' => 'Admin']);
    $user->roles()->attach($role);

    return $user->fresh();
}

/**
 * @param  list<string>  $abilities
 * @return array<string, mixed>
 */
function issueFor(User $admin, array $abilities = ['read'], string $name = 'Exporter'): array
{
    return app(IssueIntegrationToken::class)->handle($admin, $name, $abilities);
}

/**
 * @return TestResponse<Response>
 */
function inertiaGet(string $uri): TestResponse
{
    return test()->get($uri, ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Inertia\Inertia::getVersion()]);
}

/**
 * @return array<string, mixed>
 */
function bearer(string $plainText): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$plainText];
}

it('issues via the API showing plaintext once and storing only the hash', function () {
    Sanctum::actingAs(tokenAdmin());

    $response = $this->postJson('/api/v1/admin/tokens', ['name' => 'Exporter', 'abilities' => ['read', 'export']])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Exporter')
        ->assertJsonPath('data.abilities', ['read', 'export'])
        ->assertJsonMissingPath('data.token');

    $plain = $response->json('meta.token');
    $stored = PersonalAccessToken::query()->firstOrFail();
    expect($stored->token)->toBe(hash('sha256', explode('|', $plain, 2)[1]))->and($stored->token)->not->toBe($plain)
        ->and($stored->tokenable)->toBeInstanceOf(Integration::class)
        ->and($stored->abilities)->toBe(['read', 'export']);

    $list = $this->getJson('/api/v1/admin/tokens')->assertOk();
    expect(json_encode($list->json()))->not->toContain($plain)->not->toContain($stored->token);
    expect($list->json('data.0'))->toHaveKeys(['id', 'name', 'abilities', 'last_used_at'])->not->toHaveKey('token');
});

it('issues via the web flashing the value once and listing without it', function () {
    $this->actingAs(tokenAdmin());

    $this->post('/admin/tokens', ['name' => 'Exporter', 'abilities' => ['export']])->assertRedirect();
    $plain = session('issued_token.value');
    expect(PersonalAccessToken::query()->firstOrFail()->token)->toBe(hash('sha256', explode('|', $plain, 2)[1]));

    inertiaGet('/admin/tokens')
        ->assertJsonPath('component', 'Admin/Tokens/Index')
        ->assertJsonPath('props.issued.value', $plain)
        ->assertJsonCount(1, 'props.tokens.data')
        ->assertJsonPath('props.tokens.data.0.name', 'Exporter')
        ->assertJsonMissingPath('props.tokens.data.0.token');

    inertiaGet('/admin/tokens')->assertJsonPath('props.issued', null);
});

it('refuses non-admins and anonymous visitors', function () {
    $payload = ['name' => 'X', 'abilities' => ['read']];

    $this->postJson('/api/v1/admin/tokens', $payload)->assertUnauthorized();
    $this->getJson('/api/v1/admin/tokens')->assertUnauthorized();
    $this->post('/admin/tokens', $payload)->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create());
    $this->post('/admin/tokens', $payload)->assertForbidden();
    $this->get('/admin/tokens')->assertForbidden();

    $existing = Integration::factory()->create();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/admin/tokens', $payload)->assertForbidden();
    $this->deleteJson("/api/v1/admin/tokens/{$existing->id}")->assertForbidden();
    expect(Integration::query()->count())->toBe(1)->and($existing->fresh()->isRevoked())->toBeFalse();
});

it('authenticates a valid token, touches last used, and enforces ability', function () {
    $admin = tokenAdmin();
    ['token' => $token, 'plainText' => $plain] = issueFor($admin, ['read']);
    expect($token->lastUsedAt())->toBeNull();

    $this->getJson('/api/v1/integration/ping', bearer($plain))->assertOk()->assertJsonPath('data.status', 'ok');
    expect($token->fresh()->load('tokens')->lastUsedAt())->not->toBeNull();

    ['plainText' => $exportOnly] = issueFor($admin, ['export'], 'Export only');
    $this->getJson('/api/v1/integration/ping', bearer($exportOnly))->assertForbidden();
});

it('rejects unknown, missing and revoked tokens with 401', function () {
    $admin = tokenAdmin();
    ['token' => $token, 'plainText' => $plain] = issueFor($admin);

    $this->getJson('/api/v1/integration/ping')->assertUnauthorized();
    $this->getJson('/api/v1/integration/ping', bearer('mpi_nope'))->assertUnauthorized();

    app(RevokeIntegrationToken::class)->handle($admin, $token);
    $this->getJson('/api/v1/integration/ping', bearer($plain))->assertUnauthorized();
    expect($token->fresh()->isRevoked())->toBeTrue()->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('revokes via API and web through the same action', function () {
    $admin = tokenAdmin();
    ['token' => $a, 'plainText' => $plainA] = issueFor($admin, ['read'], 'A');
    ['token' => $b, 'plainText' => $plainB] = issueFor($admin, ['read'], 'B');

    Sanctum::actingAs($admin);
    $this->deleteJson("/api/v1/admin/tokens/{$a->id}")->assertOk()->assertJsonPath('data.revoked', true);
    $this->actingAs($admin)->delete("/admin/tokens/{$b->id}")->assertRedirect();

    $this->getJson('/api/v1/integration/ping', bearer($plainA))->assertUnauthorized();
    $this->getJson('/api/v1/integration/ping', bearer($plainB))->assertUnauthorized();
    expect(AdminAuditEntry::query()->where('action', 'integration_token.revoked')->count())->toBe(2);
});

it('keeps token kinds separate in both directions', function () {
    $admin = tokenAdmin();
    ['plainText' => $integration] = issueFor($admin);
    $sanctum = $admin->createToken('personal')->plainTextToken;

    $this->getJson('/api/v1/integration/ping', bearer($sanctum))->assertForbidden();
    $this->getJson('/api/v1/admin/tokens', bearer($integration))->assertForbidden();
    $this->getJson('/api/v1/admin/tokens', bearer($sanctum))->assertOk();
});

it('refuses a Player Token on integration endpoints', function () {
    $party = Party::factory()->create();
    $player = $party->createToken('Stage', ['player:connect', 'read'])->plainTextToken;

    $this->getJson('/api/v1/integration/ping', bearer($player))->assertForbidden();
});

it('refuses an Integration Token on the Player channel', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
    require base_path('routes/channels.php');
    $party = Party::factory()->create();
    ['plainText' => $plain] = issueFor(tokenAdmin(), ['read', 'export']);

    $this->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-player.'.$party->code], bearer($plain))
        ->assertForbidden();
});

it('records audit entries without the secret', function () {
    $admin = tokenAdmin();
    ['token' => $token, 'plainText' => $plain] = issueFor($admin, ['read', 'export'], 'Exporter');
    app(RevokeIntegrationToken::class)->handle($admin, $token);

    $entries = AdminAuditEntry::query()->orderBy('id')->get();
    expect($entries->pluck('action')->all())->toBe(['integration_token.issued', 'integration_token.revoked']);
    expect($entries[0]->meta)->toMatchArray(['name' => 'Exporter', 'abilities' => 'read,export']);
    expect(json_encode($entries->toArray()))->not->toContain($plain);
});

it('validates issue requests on both surfaces', function (array $payload, string $field) {
    $this->actingAs(tokenAdmin());
    $this->postJson('/admin/tokens', $payload)->assertJsonValidationErrors($field);
    $this->postJson('/api/v1/admin/tokens', $payload)->assertJsonValidationErrors($field);
    expect(Integration::query()->count())->toBe(0);
})->with([
    'missing name' => [['abilities' => ['read']], 'name'],
    'overlong name' => [['name' => str_repeat('a', 101), 'abilities' => ['read']], 'name'],
    'no abilities' => [['name' => 'X'], 'abilities'],
    'empty abilities' => [['name' => 'X', 'abilities' => []], 'abilities'],
    'unknown ability' => [['name' => 'X', 'abilities' => ['root']], 'abilities.0'],
]);

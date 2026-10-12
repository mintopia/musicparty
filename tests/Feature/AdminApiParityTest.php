<?php

use App\Domain\Admin\Actions\IssueIntegrationToken;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

function parityAdmin(): User
{
    return User::factory()->withRole('admin')->create();
}

/**
 * @param  list<string>  $abilities
 * @return array<string, string>
 */
function parityBearer(array $abilities, ?User $issuer = null): array
{
    ['plainText' => $plain] = app(IssueIntegrationToken::class)->handle($issuer ?? parityAdmin(), 'Automation', $abilities);
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$plain];
}

it('shows a user with accounts to an admin', function (): void {
    $user = User::factory()->create();
    $account = LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => SocialProvider::factory()->create(['code' => 'p'.uniqid()])->id]);
    Sanctum::actingAs(parityAdmin());

    $this->getJson("/api/v1/admin/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.accounts.0.id', $account->id);
});

it('updates a user profile', function (): void {
    $user = User::factory()->create(['nickname' => 'Old']);
    Sanctum::actingAs(parityAdmin());

    $this->patchJson("/api/v1/admin/users/{$user->id}", ['nickname' => 'Fresh', 'agreed_terms' => true, 'completed_signup' => true])
        ->assertOk()
        ->assertJsonPath('data.nickname', 'Fresh')
        ->assertJsonPath('data.agreed_terms', true)
        ->assertJsonPath('data.completed_signup', true);

    expect($user->refresh()->nickname)->toBe('Fresh');
});

it('rejects invalid or duplicate nicknames on update', function (): void {
    User::factory()->create(['nickname' => 'Taken']);
    $user = User::factory()->create();
    Sanctum::actingAs(parityAdmin());

    $this->patchJson("/api/v1/admin/users/{$user->id}", ['nickname' => 'Taken'])->assertUnprocessable()->assertJsonValidationErrors('nickname');
    $this->patchJson("/api/v1/admin/users/{$user->id}", ['nickname' => str_repeat('a', 33)])->assertUnprocessable()->assertJsonValidationErrors('nickname');
});

it('deletes a user only with confirmation and never the caller', function (): void {
    $admin = parityAdmin();
    $user = User::factory()->create();
    Sanctum::actingAs($admin);

    $this->deleteJson("/api/v1/admin/users/{$user->id}")->assertUnprocessable()->assertJsonValidationErrors('confirm');
    $this->deleteJson("/api/v1/admin/users/{$admin->id}", ['confirm' => 'delete'])->assertUnprocessable();
    $this->deleteJson("/api/v1/admin/users/{$user->id}", ['confirm' => 'delete'])->assertNoContent();

    expect(User::query()->find($user->id))->toBeNull();
});

it('deletes a linked account scoped to its user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => SocialProvider::factory()->create(['code' => 'p'.uniqid()])->id]);
    $account = LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => SocialProvider::factory()->create(['code' => 'p'.uniqid()])->id]);
    Sanctum::actingAs(parityAdmin());

    $this->deleteJson("/api/v1/admin/users/{$other->id}/linked-accounts/{$account->id}")->assertNotFound();
    $this->deleteJson("/api/v1/admin/users/{$user->id}/linked-accounts/{$account->id}")->assertNoContent();

    expect(LinkedAccount::query()->find($account->id))->toBeNull();
});

it('lists the mods catalogue', function (): void {
    Sanctum::actingAs(parityAdmin());

    $this->getJson('/api/v1/admin/mods')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'description', 'definitions']]]);
});

it('saves passport host and spotify search credentials and the auth flag', function (string $code, array $settings): void {
    Sanctum::actingAs(parityAdmin());

    $this->putJson("/api/v1/admin/providers/{$code}", ['enabled' => true, 'auth_enabled' => false, 'settings' => $settings])
        ->assertOk()
        ->assertJsonPath('data.code', $code)
        ->assertJsonPath('data.enabled', true);

    $this->getJson('/api/v1/admin/providers')
        ->assertOk()
        ->assertJsonFragment(['code' => $code]);

    $provider = SocialProvider::query()->where('code', $code)->firstOrFail()->load('settings');

    foreach (array_diff_key($settings, ['client_secret' => 1]) as $key => $value) {
        expect($provider->settings->firstWhere('code', $key)?->value)->toBe($value);
    }
})->with([
    'passport' => ['laravelpassport', ['host' => 'https://passport.example.test', 'client_id' => 'abc', 'client_secret' => 'shh']],
    'spotify search' => ['spotifysearch', ['client_id' => 'search-id', 'client_secret' => 'shh']],
]);

/**
 * @param  array<string, mixed>  $payload
 * @return array{0: string, 1: array<string, mixed>}
 */
function parityResolve(string $uri, array $payload): array
{
    $user = User::factory()->create();
    $replacements = ['{user}' => (string) $user->id, '{role}' => 'admin'];

    if (str_contains($uri, '{account}')) {
        LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => SocialProvider::factory()->create(['code' => 'p'.uniqid()])->id]);
        $replacements['{account}'] = (string) LinkedAccount::factory()->create(['user_id' => $user->id, 'social_provider_id' => SocialProvider::factory()->create(['code' => 'p'.uniqid()])->id])->id;
    }

    if (str_contains($uri, '{party}')) {
        $replacements['{party}'] = (string) Party::factory()->create()->id;
    }

    if (str_contains($uri, '{provider}')) {
        $replacements['{provider}'] = SocialProvider::factory()->create(['code' => 'spotifysearch'])->code;
    }

    return [strtr($uri, $replacements), $payload];
}

dataset('adminEndpoints', [
    'users' => ['getJson', '/api/v1/admin/users', []],
    'show' => ['getJson', '/api/v1/admin/users/{user}', []],
    'update' => ['patchJson', '/api/v1/admin/users/{user}', ['nickname' => 'Via Token']],
    'delete' => ['deleteJson', '/api/v1/admin/users/{user}', ['confirm' => 'delete']],
    'linked account delete' => ['deleteJson', '/api/v1/admin/users/{user}/linked-accounts/{account}', []],
    'suspend' => ['postJson', '/api/v1/admin/users/{user}/suspension', []],
    'unsuspend' => ['deleteJson', '/api/v1/admin/users/{user}/suspension', []],
    'grant role' => ['postJson', '/api/v1/admin/users/{user}/roles', ['role' => 'admin']],
    'revoke role' => ['deleteJson', '/api/v1/admin/users/{user}/roles/{role}', []],
    'mods' => ['getJson', '/api/v1/admin/mods', []],
    'providers' => ['getJson', '/api/v1/admin/providers', []],
    'provider update' => ['putJson', '/api/v1/admin/providers/{provider}', ['enabled' => true, 'auth_enabled' => false, 'settings' => ['client_id' => 'id', 'client_secret' => 'shh']]],
    'settings' => ['getJson', '/api/v1/admin/settings', []],
    'settings update' => ['postJson', '/api/v1/admin/settings', ['name' => 'Renamed']],
    'act as host' => ['postJson', '/api/v1/admin/parties/{party}/act-as-host', []],
    'leave host' => ['deleteJson', '/api/v1/admin/parties/{party}/act-as-host', []],
    'theme' => ['getJson', '/api/v1/admin/theme', []],
    'theme save' => ['putJson', '/api/v1/admin/theme', []],
    'theme reset' => ['deleteJson', '/api/v1/admin/theme', []],
]);

it('allows an integration token with the admin ability', function (string $method, string $uri, array $payload): void {
    [$resolved, $body] = parityResolve($uri, $payload);
    $headers = parityBearer(['admin']);

    expect($this->withHeaders($headers)->{$method}($resolved, $body)->getStatusCode())->toBeLessThan(300);
})->with('adminEndpoints');

it('refuses an integration token without the admin ability', function (string $method, string $uri, array $payload): void {
    [$resolved, $body] = parityResolve($uri, $payload);
    $headers = parityBearer(['read', 'export']);

    $this->withHeaders($headers)->{$method}($resolved, $body)->assertForbidden();
})->with('adminEndpoints');

it('refuses non-admin users on every admin endpoint', function (string $method, string $uri, array $payload): void {
    [$resolved, $body] = parityResolve($uri, $payload);
    parityAdmin();
    Sanctum::actingAs(User::factory()->create());

    $this->{$method}($resolved, $body)->assertForbidden();
})->with('adminEndpoints');

it('attributes admin token actions to the issuing admin', function (): void {
    $issuer = parityAdmin();
    $user = User::factory()->create();
    $headers = parityBearer(['admin'], $issuer);

    $this->postJson("/api/v1/admin/users/{$user->id}/suspension", [], $headers)->assertOk();

    expect(AdminAuditEntry::query()->where('action', 'user.suspended')->first()?->admin_id)->toBe($issuer->id);
});

it('refuses an admin token whose issuer lost the admin role', function (): void {
    $issuer = parityAdmin();
    $headers = parityBearer(['admin'], $issuer);
    $issuer->roles()->detach();

    $this->getJson('/api/v1/admin/users', $headers)->assertForbidden();
});

it('never lets an integration token mint or manage tokens', function (): void {
    $headers = parityBearer(['admin', 'read', 'export']);

    $this->getJson('/api/v1/admin/tokens', $headers)->assertForbidden();
    $this->postJson('/api/v1/admin/tokens', ['name' => 'Escalate', 'abilities' => ['admin']], $headers)->assertForbidden();
});

it('lets integration tokens be issued with the admin ability by an admin user', function (): void {
    Sanctum::actingAs(parityAdmin());

    $this->postJson('/api/v1/admin/tokens', ['name' => 'Ops', 'abilities' => ['admin']])
        ->assertCreated()
        ->assertJsonPath('data.abilities', ['admin']);
});

it('refuses non-admin users and guests', function (): void {
    $this->getJson('/api/v1/admin/mods')->assertUnauthorized();
});

it('refuses non-admin users', function (): void {
    parityAdmin();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/mods')->assertForbidden();
});

<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Models\PartyMod;
use App\Domain\Mod\ModRegistry;
use App\Domain\Mod\PartyMods;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Mods\SettingsFixtureMod;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    app()->instance(ModRegistry::class, new ModRegistry);
    app(ModRegistry::class)->register(new SettingsFixtureMod);
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->other = Party::factory()->live()->create(['code' => 'WXYZ']);
});

function modActor(Party $party, string $role): User
{
    $member = PartyMember::factory()->for($party);

    return ($role === 'member' ? $member : $member->{$role}())->create()->user;
}

function enableFixture(Party $party, ?User $actor = null): void
{
    Sanctum::actingAs($actor ?? modActor($party, 'host'));
    test()->putJson("/api/v1/parties/{$party->code}/mods/settings-fixture")->assertSuccessful();
}

it('lists registered mods in the catalogue, disabled by default', function () {
    Sanctum::actingAs(modActor($this->party, 'host'));

    $this->getJson('/api/v1/parties/ABCD/mods')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', 'settings-fixture')
        ->assertJsonPath('data.0.enabled', false)
        ->assertJsonCount(5, 'data.0.definitions');
});

it('applies declared defaults on first enable and logs it', function () {
    $host = modActor($this->party, 'host');
    enableFixture($this->party, $host);

    $this->getJson('/api/v1/parties/ABCD/mods')
        ->assertJsonPath('data.0.enabled', true)
        ->assertJsonPath('data.0.settings.limit', 5)
        ->assertJsonPath('data.0.settings.note', 'hello')
        ->assertJsonPath('data.0.settings.token', null);

    $entry = PartyLogEntry::query()->where('action', 'mod.enabled')->sole();
    expect($entry->user_id)->toBe($host->id)->and($entry->subject)->toBe('settings-fixture');
});

it('keeps enablement isolated per party', function () {
    enableFixture($this->party);

    expect(app(PartyMods::class)->contextFor($this->party, 'settings-fixture'))->not->toBeNull()
        ->and(app(PartyMods::class)->contextFor($this->other, 'settings-fixture'))->toBeNull()
        ->and(app(PartyMods::class)->enabledFor($this->other))->toBe([]);
});

it('disables a mod and logs it, no-oping when already disabled', function () {
    $host = modActor($this->party, 'host');
    enableFixture($this->party, $host);

    $this->deleteJson('/api/v1/parties/ABCD/mods/settings-fixture')->assertNoContent();
    $this->deleteJson('/api/v1/parties/ABCD/mods/settings-fixture')->assertNoContent();

    expect(app(PartyMods::class)->contextFor($this->party, 'settings-fixture'))->toBeNull()
        ->and(PartyLogEntry::query()->where('action', 'mod.disabled')->count())->toBe(1);
});

it('returns 404 for an unknown mod', function () {
    Sanctum::actingAs(modActor($this->party, 'host'));

    $this->putJson('/api/v1/parties/ABCD/mods/unknown')->assertNotFound();
});

it('stores valid settings and logs changed keys without secret values', function () {
    enableFixture($this->party);

    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['limit' => 7, 'mode' => 'b', 'active' => false, 'token' => 'sk-secret-value']])
        ->assertSuccessful()
        ->assertJsonPath('data.settings.limit', 7)
        ->assertJsonPath('data.settings.mode', 'b')
        ->assertJsonPath('data.settings.active', false)
        ->assertJsonPath('data.settings.token', '********');

    $context = app(PartyMods::class)->contextFor($this->party->fresh(), 'settings-fixture');
    expect($context->settings['limit'])->toBe(7)->and($context->settings['token'])->toBe('sk-secret-value');

    $entry = PartyLogEntry::query()->where('action', 'mod.settings_updated')->sole();
    expect($entry->details['changes']['limit'])->toBe(['old' => 5, 'new' => 7])
        ->and($entry->details['changes']['token'])->toBe(['changed' => true])
        ->and(json_encode($entry->details))->not->toContain('sk-secret-value');
});

it('encrypts secrets at rest and never returns them', function () {
    enableFixture($this->party);

    $response = $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['token' => 'sk-secret-value']]);
    $raw = (string) DB::table('party_mods')->value('settings');

    expect($raw)->not->toContain('sk-secret-value')
        ->and(Crypt::decryptString(PartyMod::query()->sole()->settings['token']))->toBe('sk-secret-value')
        ->and($response->getContent())->not->toContain('sk-secret-value');
});

it('keeps a secret when the masked value is sent back', function () {
    enableFixture($this->party);
    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['token' => 'sk-secret-value']]);

    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['token' => '********', 'limit' => 2]])->assertSuccessful();

    expect(app(PartyMods::class)->contextFor($this->party->fresh(), 'settings-fixture')->settings['token'])->toBe('sk-secret-value');
});

it('rejects invalid settings and leaves the previous ones unchanged', function (array $settings, string $errorKey) {
    enableFixture($this->party);
    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['limit' => 3]])->assertSuccessful();
    $logged = PartyLogEntry::query()->count();

    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => $settings + ['limit' => 9]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);

    expect(app(PartyMods::class)->contextFor($this->party->fresh(), 'settings-fixture')->settings['limit'])->toBe(3)
        ->and(PartyLogEntry::query()->count())->toBe($logged);
})->with([
    'wrong type' => [['limit' => 'many'], 'limit'],
    'below min' => [['limit' => 0], 'limit'],
    'above max' => [['limit' => 11], 'limit'],
    'bad choice' => [['mode' => 'c'], 'mode'],
    'bad boolean' => [['active' => 'maybe'], 'active'],
    'unknown key' => [['bogus' => 1], 'bogus'],
]);

it('rejects settings for a mod that is not enabled', function () {
    Sanctum::actingAs(modActor($this->party, 'host'));

    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['limit' => 3]])->assertUnprocessable();
});

it('does not log an unchanged settings save', function () {
    enableFixture($this->party);

    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['limit' => 5]])->assertSuccessful();

    expect(PartyLogEntry::query()->where('action', 'mod.settings_updated')->count())->toBe(0);
});

it('retains settings across disable and re-enable', function () {
    enableFixture($this->party);
    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['limit' => 8]]);
    $this->deleteJson('/api/v1/parties/ABCD/mods/settings-fixture');
    $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture')->assertSuccessful();

    expect(app(PartyMods::class)->contextFor($this->party->fresh(), 'settings-fixture')->settings['limit'])->toBe(8);
});

it('allows only the host to manage mods', function (string $role, bool $allowed) {
    $actor = modActor($this->party, $role);
    Sanctum::actingAs($actor);

    $calls = [
        $this->getJson('/api/v1/parties/ABCD/mods'),
        $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture'),
        $this->putJson('/api/v1/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['limit' => 2]]),
        $this->deleteJson('/api/v1/parties/ABCD/mods/settings-fixture'),
    ];

    foreach ($calls as $call) {
        $allowed ? $call->assertSuccessful() : $call->assertForbidden();
    }
})->with([
    'host' => ['host', true],
    'moderator' => ['moderator', false],
    'member' => ['member', false],
]);

it('allows the party owner', function () {
    $owner = User::factory()->create();
    $party = Party::factory()->live()->create(['code' => 'OWNR', 'user_id' => $owner->id]);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/parties/{$party->code}/mods/settings-fixture")->assertSuccessful();
});

it('refuses unauthenticated access', function () {
    $this->getJson('/api/v1/parties/ABCD/mods')->assertUnauthorized();
});

it('serves the web mods page to the host and refuses a moderator', function () {
    $this->actingAs(modActor($this->party, 'host'))
        ->get('/parties/ABCD/mods')
        ->assertInertia(fn (Assert $page) => $page->component('Party/Mods')->where('mods.0.id', 'settings-fixture'));

    $this->actingAs(modActor($this->party, 'moderator'))->get('/parties/ABCD/mods')->assertForbidden();
});

it('manages mods through the web routes', function () {
    $this->actingAs(modActor($this->party, 'host'));

    $this->put('/parties/ABCD/mods/settings-fixture')->assertRedirect();
    $this->put('/parties/ABCD/mods/settings-fixture/settings', ['settings' => ['limit' => 4]])->assertRedirect();
    expect(app(PartyMods::class)->contextFor($this->party->fresh(), 'settings-fixture')->settings['limit'])->toBe(4);

    $this->delete('/parties/ABCD/mods/settings-fixture')->assertRedirect();
    expect(PartyMod::query()->sole()->enabled)->toBeFalse();
});

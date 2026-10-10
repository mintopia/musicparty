<?php

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Identity\Models\User;
use App\Domain\Theming\ThemeTokens;
use App\Models\InstanceTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function adminForTheme(): User
{
    $user = User::factory()->create();
    app(GrantRole::class)->handle($user, $user, 'admin');
    AdminAuditEntry::query()->delete();

    return $user->fresh();
}

it('shows the editor to admins', function (): void {
    $this->withoutVite()->actingAs(adminForTheme())->get('/admin/theme')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Admin/Theme')
            ->where('theme', ThemeTokens::defaults())
            ->where('defaults', ThemeTokens::defaults())
            ->has('fonts', 4)
            ->has('tokens', 14)
            ->where('tokens.0', ['key' => 'primary', 'label' => 'Primary'])
            ->where('warnings', []));
});

it('saves via the web and merges over defaults', function (): void {
    $admin = adminForTheme();

    $this->actingAs($admin)->from('/admin/theme')
        ->put('/admin/theme', ['light' => ['primary' => '#AABBCC'], 'font' => 'serif'])
        ->assertRedirect('/admin/theme');

    $stored = InstanceTheme::query()->sole()->tokens;
    expect($stored['light']['primary'])->toBe('#aabbcc')
        ->and($stored['light']['accent'])->toBe('#2fb344')
        ->and($stored['font'])->toBe('serif');
    $audit = AdminAuditEntry::query()->sole();
    expect($audit->action)->toBe('theme.updated')->and($audit->admin_id)->toBe($admin->id);
});

it('saves via the api and returns the theme with warnings', function (): void {
    Sanctum::actingAs(adminForTheme());

    $this->putJson('/api/v1/admin/theme', ['light' => ['text' => '#777777', 'background' => '#ffffff'], 'dark' => ['primary' => '#101010'], 'font' => 'mono'])
        ->assertOk()
        ->assertJsonPath('data.light.text', '#777777')
        ->assertJsonPath('data.dark.primary', '#101010')
        ->assertJsonPath('data.font', 'mono')
        ->assertJsonPath('data.warnings.0.scheme', 'light')
        ->assertJsonPath('data.warnings.0.pair', 'text/background')
        ->assertJsonPath('data.warnings.0.ratio', 4.48);

    $this->getJson('/api/v1/admin/theme')->assertOk()->assertJsonPath('data.dark.primary', '#101010');
    expect(AdminAuditEntry::query()->where('action', 'theme.updated')->count())->toBe(1);
});

it('shows contrast warnings on the web editor without blocking the save', function (): void {
    $admin = adminForTheme();
    $this->actingAs($admin)->put('/admin/theme', ['light' => ['text' => '#cccccc']])->assertRedirect();

    $this->withoutVite()->actingAs($admin)->get('/admin/theme')
        ->assertInertia(fn (Assert $page): Assert => $page->has('warnings', 2)->where('warnings.0.scheme', 'light'));
});

it('rejects invalid input on web and api and stores nothing', function (array $payload): void {
    $admin = adminForTheme();

    $this->actingAs($admin)->put('/admin/theme', $payload)->assertSessionHasErrors();
    Sanctum::actingAs($admin);
    $this->putJson('/api/v1/admin/theme', $payload)->assertUnprocessable();

    expect(InstanceTheme::query()->count())->toBe(0)
        ->and(AdminAuditEntry::query()->count())->toBe(0);
})->with([
    'bad hex' => [['light' => ['primary' => 'blue']]],
    'short hex' => [['dark' => ['primary' => '#fff']]],
    'breakout' => [['light' => ['primary' => 'red;}body{display:none}']]],
    'url' => [['light' => ['primary' => 'url(http://x)']]],
    'embedded newline' => [['light' => ['primary' => "#fff\nfff"]]],
    'unknown key' => [['light' => ['evil' => '#ffffff']]],
    'unknown font' => [['font' => 'comic-sans']],
    'font injection' => [['font' => 'serif;}body{x:y']],
    'non-array scheme' => [['light' => 'red']],
]);

it('resets to defaults on web and api', function (): void {
    $admin = adminForTheme();
    $this->actingAs($admin)->put('/admin/theme', ['light' => ['primary' => '#010101']]);

    $this->actingAs($admin)->delete('/admin/theme')->assertRedirect();
    expect(InstanceTheme::query()->count())->toBe(0)
        ->and(AdminAuditEntry::query()->where('action', 'theme.reset')->count())->toBe(1);

    Sanctum::actingAs($admin);
    $this->putJson('/api/v1/admin/theme', ['light' => ['primary' => '#020202']])->assertOk();
    $this->deleteJson('/api/v1/admin/theme')->assertOk()
        ->assertJsonPath('data.light.primary', ThemeTokens::defaults()['light']['primary'])
        ->assertJsonPath('data.font', 'inter');
    expect(InstanceTheme::query()->count())->toBe(0);
});

it('refuses non-admins and guests', function (): void {
    $user = User::factory()->create();

    foreach (['get', 'put', 'delete'] as $method) {
        $this->actingAs($user)->{$method}('/admin/theme')->assertForbidden();
        $this->actingAs($user)->{$method.'Json'}('/api/v1/admin/theme')->assertForbidden();
    }
});

it('refuses guests', function (): void {
    $this->get('/admin/theme')->assertRedirect(route('login'));
    $this->getJson('/api/v1/admin/theme')->assertUnauthorized();
    $this->putJson('/api/v1/admin/theme', [])->assertUnauthorized();
    $this->deleteJson('/api/v1/admin/theme')->assertUnauthorized();
});

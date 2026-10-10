<?php

use App\Domain\Admin\Actions\GrantRole;
use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Admin\Models\Setting;
use App\Domain\Admin\SiteSettings;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Theming\Actions\GetPartyTheme;
use App\Domain\Theming\Actions\RenderPartyThemeCss;
use App\Domain\Theming\Actions\ResetPartyTheme;
use App\Domain\Theming\Actions\UpdatePartyTheme;
use App\Domain\Theming\ThemeTokens;
use App\Enums\SettingType;
use App\Events\Party\ThemeUpdatedEvent;
use App\Models\InstanceTheme;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function quietParty(array $attributes = []): Party
{
    return Party::withoutEvents(fn (): Party => Party::factory()->create($attributes));
}

function partyMemberWithRole(Party $party, string $role): User
{
    $state = match ($role) {
        'owner', 'host' => 'host',
        'moderator' => 'moderator',
        'vip' => 'vip',
        default => null,
    };
    $factory = PartyMember::factory()->for($party);

    return ($state === null ? $factory : $factory->{$state}())->create()->user;
}

function createParty(array $attributes = []): Party
{
    return quietParty($attributes);
}

function hostedParty(array $theme = []): array
{
    $party = createParty($theme === [] ? [] : ['theme' => $theme]);

    return [$party, partyMemberWithRole($party, 'owner')];
}

beforeEach(function (): void {
    Storage::fake(SiteSettings::disk());
});

it('composes the effective theme from the instance theme and party overrides', function (): void {
    InstanceTheme::factory()->create(['tokens' => ['light' => ['primary' => '#111111', 'accent' => '#222222'], 'dark' => [], 'font' => 'serif']]);
    $party = createParty(['theme' => ['light' => ['primary' => '#abcdef'], 'dark' => ['text' => '#fefefe'], 'font' => 'mono']]);

    $theme = app(GetPartyTheme::class)->handle($party);

    expect($theme['light']['primary'])->toBe('#abcdef')
        ->and($theme['light']['accent'])->toBe('#222222')
        ->and($theme['dark']['text'])->toBe('#fefefe')
        ->and($theme['font'])->toBe('mono')
        ->and($theme['tv_layout'])->toBe('default')
        ->and($theme['overrides'])->toBe(['light' => ['primary' => '#abcdef'], 'dark' => ['text' => '#fefefe'], 'font' => 'mono']);
});

it('inherits the instance theme when the party has no overrides', function (): void {
    $theme = app(GetPartyTheme::class)->handle(createParty());

    expect($theme['light'])->toBe(ThemeTokens::defaults()['light'])
        ->and($theme['font'])->toBe(ThemeTokens::DEFAULT_FONT)
        ->and($theme['overrides'])->toBe(['light' => [], 'dark' => [], 'font' => null]);
});

it('ignores hostile stored overrides and unknown layouts', function (): void {
    $party = createParty(['theme' => ['light' => ['primary' => 'red;}body{x:y', 'muted' => '#123456'], 'font' => 'url(x)'], 'tv_layout' => 'custom']);

    $theme = app(GetPartyTheme::class)->handle($party);

    expect($theme['light']['primary'])->toBe(ThemeTokens::defaults()['light']['primary'])
        ->and($theme['light']['muted'])->toBe(ThemeTokens::defaults()['light']['muted'])
        ->and($theme['font'])->toBe(ThemeTokens::DEFAULT_FONT)
        ->and($theme['tv_layout'])->toBe('default');
});

it('falls back to the instance logo until the party uploads one', function (): void {
    (new Setting)->forceFill(['code' => 'logo-light', 'name' => 'x', 'encrypted' => false, 'hidden' => false, 'validation' => '', 'type' => SettingType::stString, 'value' => 'site/logo.png'])->save();
    [$party, $host] = hostedParty();

    expect(app(GetPartyTheme::class)->handle($party)['logo_url'])->toContain('site/logo.png');

    app(UpdatePartyTheme::class)->handle($host, $party, ['logo' => UploadedFile::fake()->image('logo.png')]);
    $party->refresh();
    expect(app(GetPartyTheme::class)->handle($party)['logo_url'])->toContain('parties/'.$party->code.'/theme/');

    app(UpdatePartyTheme::class)->handle($host, $party, ['remove_logo' => true]);
    expect(app(GetPartyTheme::class)->handle($party->refresh())['logo_url'])->toContain('site/logo.png');
});

it('renders the effective theme as variable declarations only', function (): void {
    $party = createParty(['theme' => ['light' => ['primary' => '#abcdef'], 'font' => 'serif']]);

    $css = app(RenderPartyThemeCss::class)->handle($party);

    expect($css)->toMatch('/\A:root\{(--[a-z-]+:[^;{}]+;)+\}\.dark\{(--[a-z-]+:[^;{}]+;)+\}\z/')
        ->and($css)->toContain('--color-primary:#abcdef;')
        ->and($css)->toContain('--font-sans:'.ThemeTokens::FONTS['serif']['stack'].';');
});

it('updates overrides, font and layout via the api and returns the effective theme', function (): void {
    Event::fake([ThemeUpdatedEvent::class]);
    [$party, $host] = hostedParty();
    Sanctum::actingAs($host);

    $this->putJson("/api/v1/parties/{$party->code}/theme", [
        'light' => ['primary' => '#AABBCC', 'text' => '#777777', 'background' => '#ffffff'],
        'dark' => ['danger' => '#101010'],
        'font' => 'mono',
        'tv_layout' => 'compact',
    ])
        ->assertOk()
        ->assertJsonPath('data.light.primary', '#aabbcc')
        ->assertJsonPath('data.dark.danger', '#101010')
        ->assertJsonPath('data.font', 'mono')
        ->assertJsonPath('data.tv_layout', 'compact')
        ->assertJsonPath('data.overrides.light.primary', '#aabbcc')
        ->assertJsonPath('data.warnings.0.pair', 'text/background');

    Event::assertDispatched(ThemeUpdatedEvent::class, fn (ThemeUpdatedEvent $event): bool => $event->partyCode === $party->code);
    $this->getJson("/api/v1/parties/{$party->code}/theme")->assertOk()->assertJsonPath('data.light.primary', '#aabbcc');
});

it('behaves identically through the web and api', function (): void {
    [$partyWeb, $hostWeb] = hostedParty();
    [$partyApi, $hostApi] = hostedParty();
    $payload = ['light' => ['primary' => '#123456'], 'dark' => ['accent' => '#654321'], 'font' => 'system', 'tv_layout' => 'queue-focus'];

    $this->actingAs($hostWeb)->from("/parties/{$partyWeb->code}/theme")
        ->put("/parties/{$partyWeb->code}/theme", $payload)
        ->assertRedirect("/parties/{$partyWeb->code}/theme");
    Sanctum::actingAs($hostApi);
    $this->putJson("/api/v1/parties/{$partyApi->code}/theme", $payload)->assertOk();

    expect($partyWeb->refresh()->theme)->toBe($partyApi->refresh()->theme)
        ->and($partyWeb->tv_layout)->toBe($partyApi->tv_layout);
});

it('clears an override so the token inherits again', function (): void {
    [$party, $host] = hostedParty(['light' => ['primary' => '#abcdef', 'accent' => '#fedcba'], 'font' => 'serif']);

    app(UpdatePartyTheme::class)->handle($host, $party, ['light' => ['primary' => null], 'font' => '']);

    $theme = app(GetPartyTheme::class)->handle($party->refresh());
    expect($theme['light']['primary'])->toBe(ThemeTokens::defaults()['light']['primary'])
        ->and($theme['light']['accent'])->toBe('#fedcba')
        ->and($theme['font'])->toBe(ThemeTokens::DEFAULT_FONT)
        ->and($party->theme)->toBe(['light' => ['accent' => '#fedcba']]);

    app(UpdatePartyTheme::class)->handle($host, $party, ['light' => ['accent' => null]]);
    expect($party->refresh()->theme)->toBeNull();
});

it('resets the whole party theme including assets', function (): void {
    Event::fake([ThemeUpdatedEvent::class]);
    [$party, $host] = hostedParty(['light' => ['primary' => '#abcdef']]);
    app(UpdatePartyTheme::class)->handle($host, $party, ['logo' => UploadedFile::fake()->image('l.png'), 'tv_layout' => 'compact']);
    $path = $party->refresh()->theme_logo_path;

    app(ResetPartyTheme::class)->handle($host, $party);

    $party->refresh();
    expect($party->theme)->toBeNull()
        ->and($party->theme_logo_path)->toBeNull()
        ->and($party->tv_layout)->toBe('default');
    Storage::disk(SiteSettings::disk())->assertMissing($path);
    Event::assertDispatchedTimes(ThemeUpdatedEvent::class, 2);
});

it('resets via the api and the web', function (): void {
    [$party, $host] = hostedParty(['light' => ['primary' => '#abcdef']]);

    Sanctum::actingAs($host);
    $this->deleteJson("/api/v1/parties/{$party->code}/theme")->assertOk()->assertJsonPath('data.overrides.light', []);

    $party->forceFill(['theme' => ['font' => 'mono']])->save();
    $this->actingAs($host)->delete("/parties/{$party->code}/theme")->assertRedirect();
    expect($party->refresh()->theme)->toBeNull();
});

it('rejects invalid input on web and api and stores nothing', function (array $payload): void {
    Event::fake([ThemeUpdatedEvent::class]);
    [$party, $host] = hostedParty(['light' => ['primary' => '#abcdef']]);

    $this->actingAs($host)->put("/parties/{$party->code}/theme", $payload)->assertSessionHasErrors();
    Sanctum::actingAs($host);
    $this->putJson("/api/v1/parties/{$party->code}/theme", $payload)->assertUnprocessable();

    expect($party->refresh()->theme)->toBe(['light' => ['primary' => '#abcdef']])->and($party->tv_layout)->toBe('default');
    Event::assertNotDispatched(ThemeUpdatedEvent::class);
})->with([
    'non-party token' => [['light' => ['muted' => '#123456']]],
    'sidebar token dark' => [['dark' => ['sidebar' => '#123456']]],
    'unknown scheme key' => [['light' => ['nope' => '#123456']]],
    'short hex' => [['light' => ['primary' => '#123']]],
    'named colour' => [['light' => ['primary' => 'red']]],
    'css declaration' => [['dark' => ['primary' => '#123456;background:url(x)']]],
    'closing brace' => [['light' => ['text' => '#123456}body{display:none']]],
    'html' => [['light' => ['accent' => '<script>alert(1)</script>']]],
    'array value' => [['light' => ['primary' => ['#123456']]]],
    'unknown font' => [['font' => 'Comic Sans']],
    'css font' => [['font' => 'serif;}body{x:y']],
    'unknown layout' => [['tv_layout' => 'custom']],
    'html layout' => [['tv_layout' => '<div>']],
]);

it('refuses everyone but the host', function (string $who): void {
    Event::fake([ThemeUpdatedEvent::class]);
    [$party] = hostedParty();
    $user = $who === 'guest' ? null : ($who === 'other' ? User::factory()->create() : partyMemberWithRole($party, $who));
    $payload = ['light' => ['primary' => '#123456']];

    if ($user === null) {
        $this->put("/parties/{$party->code}/theme", $payload)->assertRedirect('/login');
        $this->putJson("/api/v1/parties/{$party->code}/theme", $payload)->assertUnauthorized();
    } else {
        $this->actingAs($user)->put("/parties/{$party->code}/theme", $payload)->assertForbidden();
        $this->actingAs($user)->get("/parties/{$party->code}/theme")->assertForbidden();
        Sanctum::actingAs($user);
        $this->putJson("/api/v1/parties/{$party->code}/theme", $payload)->assertForbidden();
        $this->deleteJson("/api/v1/parties/{$party->code}/theme")->assertForbidden();
        $this->putJson("/api/v1/parties/{$party->code}/theme", ['font' => 'nope'])->assertForbidden();
        expect(fn () => app(UpdatePartyTheme::class)->handle($user, $party, $payload))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ResetPartyTheme::class)->handle($user, $party))->toThrow(AuthorizationException::class);
    }

    expect($party->refresh()->theme)->toBeNull();
    Event::assertNotDispatched(ThemeUpdatedEvent::class);
})->with(['guest', 'moderator', 'vip', 'member', 'other']);

it('lets an admin acting as host edit the theme', function (): void {
    $party = createParty();
    $admin = User::factory()->create();
    app(GrantRole::class)->handle($admin, $admin, 'admin');
    AdminHostSession::factory()->create(['user_id' => $admin->id, 'party_id' => $party->id]);

    Sanctum::actingAs($admin->fresh());
    $this->putJson("/api/v1/parties/{$party->code}/theme", ['tv_layout' => 'compact'])->assertOk();
});

it('uploads, replaces and removes a logo and background', function (): void {
    [$party, $host] = hostedParty();
    $disk = Storage::disk(SiteSettings::disk());
    Sanctum::actingAs($host);

    $this->post("/api/v1/parties/{$party->code}/theme", ['logo' => UploadedFile::fake()->image('a.png'), 'background' => UploadedFile::fake()->image('b.jpg')], ['Accept' => 'application/json'])->assertOk();
    $party->refresh();
    [$logo, $background] = [$party->theme_logo_path, $party->theme_background_path];
    $disk->assertExists($logo);
    $disk->assertExists($background);

    $this->post("/api/v1/parties/{$party->code}/theme", ['logo' => UploadedFile::fake()->image('c.webp')], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.logo_url', $disk->url($party->refresh()->theme_logo_path));
    $disk->assertMissing($logo);
    $disk->assertExists($background);
    $disk->assertExists($party->theme_logo_path);

    $this->putJson("/api/v1/parties/{$party->code}/theme", ['remove_logo' => true, 'remove_background' => true])
        ->assertOk()->assertJsonPath('data.background_url', null);
    $party->refresh();
    expect($party->theme_logo_path)->toBeNull()->and($party->theme_background_path)->toBeNull();
    $disk->assertMissing($background);
});

it('accepts uploads through the web form', function (): void {
    [$party, $host] = hostedParty();

    $this->actingAs($host)->post("/parties/{$party->code}/theme", ['_method' => 'PUT', 'logo' => UploadedFile::fake()->image('a.png')])->assertRedirect();

    Storage::disk(SiteSettings::disk())->assertExists($party->refresh()->theme_logo_path);
});

it('rejects invalid uploads and keeps the previous asset', function (string $field, UploadedFile $file): void {
    [$party, $host] = hostedParty();
    app(UpdatePartyTheme::class)->handle($host, $party, ['logo' => UploadedFile::fake()->image('a.png'), 'background' => UploadedFile::fake()->image('b.png')]);
    $party->refresh();
    $before = [$party->theme_logo_path, $party->theme_background_path];
    Sanctum::actingAs($host);

    $this->post("/api/v1/parties/{$party->code}/theme", [$field => $file], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors($field);

    $party->refresh();
    expect([$party->theme_logo_path, $party->theme_background_path])->toBe($before);
    foreach ($before as $path) {
        Storage::disk(SiteSettings::disk())->assertExists($path);
    }
})->with([
    'pdf logo' => ['logo', fn () => UploadedFile::fake()->create('l.pdf', 10, 'application/pdf')],
    'svg logo' => ['logo', fn () => UploadedFile::fake()->createWithContent('l.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>')],
    'php background' => ['background', fn () => UploadedFile::fake()->create('b.php', 1, 'text/x-php')],
    'oversize logo' => ['logo', fn () => UploadedFile::fake()->create('l.png', 2049, 'image/png')],
    'oversize background' => ['background', fn () => UploadedFile::fake()->create('b.jpg', 4096, 'image/jpeg')],
]);

it('accepts a logo exactly at the size limit', function (): void {
    [$party, $host] = hostedParty();
    Sanctum::actingAs($host);

    $this->post("/api/v1/parties/{$party->code}/theme", ['logo' => UploadedFile::fake()->create('l.png', 2048, 'image/png')], ['Accept' => 'application/json'])->assertOk();
});

it('renders the editor for the host with effective and override data', function (): void {
    [$party, $host] = hostedParty(['light' => ['primary' => '#abcdef']]);

    $this->withoutVite()->actingAs($host)->get("/parties/{$party->code}/theme")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Party/Theme')
            ->where('party.code', $party->code)
            ->where('theme.light.primary', '#abcdef')
            ->where('theme.overrides.light.primary', '#abcdef')
            ->has('fonts', 4)
            ->has('tokens', 6)
            ->where('tokens.0', ['key' => 'primary', 'label' => 'Primary'])
            ->has('layouts', 4)
            ->where('warnings', []));
});

it('shows the tv page anonymously with the party theme and no admin data', function (): void {
    $party = createParty(['theme' => ['light' => ['primary' => '#abcdef']], 'tv_layout' => 'fullscreen-art']);

    $response = $this->withoutVite()->get("/parties/{$party->code}/tv");

    $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('Party/Tv')
        ->where('party.code', $party->code)
        ->where('party.name', $party->name)
        ->where('theme.tv_layout', 'fullscreen-art')
        ->where('theme.light.primary', '#abcdef'));
    expect($response->getContent())->toContain('--color-primary:#abcdef;');
});

it('returns not found for an unknown party tv screen', function (): void {
    $this->withoutVite()->get('/parties/NOPE00/tv')->assertNotFound();
});

it('does not apply a party theme to other pages', function (): void {
    createParty(['theme' => ['light' => ['primary' => '#abcdef']]]);

    expect($this->withoutVite()->get('/login')->getContent())->not->toContain('#abcdef');
});

it('broadcasts the effective theme on the public party channel', function (): void {
    $party = createParty(['theme' => ['light' => ['primary' => '#abcdef']]]);
    $event = new ThemeUpdatedEvent($party->code);

    expect($event->broadcastAs())->toBe('ThemeUpdated')
        ->and($event->broadcastOn()[0]->name)->toBe("party.{$party->code}")
        ->and($event->broadcastWith()['light']['primary'])->toBe('#abcdef');
});

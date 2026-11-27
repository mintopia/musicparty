<?php

use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function themeAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));

    return $user;
}

it('serves the theme stylesheet as cacheable text/css', function () {
    $theme = Theme::factory()->create([
        'name' => 'Neon',
        'tokens' => ['primary' => '#ff0000', 'nav-background' => '#00ff00', 'bogus' => 'red'],
        'css' => '.x { color: blue; }',
    ]);

    $response = $this->get($theme->stylesheetUrl())->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/css')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->headers->get('ETag'))->toContain($theme->version());
    $response->assertSee('--mp-primary: #ff0000;', false)
        ->assertSee('--mp-primary-rgb: 255, 0, 0;', false)
        ->assertSee('--mp-nav-background: #00ff00;', false)
        ->assertSee('.x { color: blue; }', false)
        ->assertDontSee('bogus');

    $stale = $this->get(route('theme.css', ['code' => 'neon', 'v' => 'stale']))->assertOk();
    expect($stale->headers->get('Cache-Control'))->not->toContain('immutable');
    $this->get($theme->stylesheetUrl(), ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
});

it('emits the token block for the light and dark scopes so it beats Tabler dark overrides', function () {
    $theme = Theme::factory()->create(['name' => 'Scopes', 'dark_mode' => true, 'tokens' => ['primary' => '#a86ef0']]);

    $css = $this->get($theme->stylesheetUrl())->assertOk()->getContent();

    expect($css)->toStartWith(":root, [data-bs-theme=light], [data-bs-theme=dark] {\n")
        ->and($css)->toContain('--tblr-link-color: var(--mp-text-primary);');
});

it('applies top-level :root rules in custom css to the dark and light scopes too', function () {
    $theme = Theme::factory()->create([
        'name' => 'Rooted',
        'dark_mode' => true,
        'css' => ":root{--tblr-link-color:#a86ef0}\n:root.x .y{--a:1}\n.z:not(:root-ish){--b:1}\n.keep{--tblr-root:1}",
    ]);

    $css = $this->get($theme->stylesheetUrl())->assertOk()->getContent();
    $scoped = ':is(:root, [data-bs-theme=light], [data-bs-theme=dark])';

    expect($css)->toContain("{$scoped}{--tblr-link-color:#a86ef0}")
        ->and($css)->toContain("{$scoped}.x .y")
        ->and($css)->toContain(':root-ish')
        ->and($css)->toContain('.keep{--tblr-root:1}')
        ->and(substr_count($css, ':is(:root'))->toBe(2);
});

it('404s for an unknown theme code', function () {
    $this->get('/theme/nope.css')->assertNotFound();
});

it('never inlines custom CSS in a Blade or Inertia page', function () {
    Theme::factory()->active()->create(['name' => 'Evil', 'css' => '</style><script>alert(1)</script>']);

    foreach ([route('inertia.shell'), route('login')] as $url) {
        $this->get($url)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('/theme/evil.css?v=', false);
    }
    $this->get(route('theme.css', 'evil'))->assertOk();
});

it('shares the active theme with Inertia and follows a change of active theme', function () {
    Theme::factory()->active()->create(['name' => 'One', 'tokens' => ['primary' => '#111111', 'nav-background' => '#222222']]);
    $two = Theme::factory()->create(['name' => 'Two', 'tokens' => ['primary' => '#333333', 'nav-background' => '#444444'], 'dark_mode' => true]);

    $this->get(route('inertia.shell'))->assertInertia(fn (Assert $page) => $page
        ->where('theme.tokens.primary', '#111111')
        ->where('theme.darkMode', false)
        ->has('theme.stylesheet'));

    $this->actingAs(themeAdmin())->put(route('admin.themes.update', $two), [
        'name' => 'Two', 'active' => 1, 'dark_mode' => 1, 'css' => '',
        'tokens' => ['primary' => '#333333', 'nav-background' => '#444444'],
    ])->assertRedirect();
    app()->forgetScopedInstances();

    $this->get(route('inertia.shell'))->assertInertia(fn (Assert $page) => $page
        ->where('theme.tokens.primary', '#333333')
        ->where('theme.darkMode', true));
    app()->forgetScopedInstances();
    $this->get(route('inertia.shell'))->assertSee('/theme/two.css?v='.$two->fresh()->version(), false)
        ->assertSee('data-bs-theme="dark"', false);
});

it('resolves the active theme once per request', function () {
    Theme::factory()->active()->create(['name' => 'Solo']);
    $this->get(route('login'));

    DB::enableQueryLog();
    $this->get(route('login'))->assertOk();
    $themeQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"themes"') || str_contains($q['query'], '`themes`'));

    expect($themeQueries)->toHaveCount(0);
});

it('creates and updates themes with tokens and validates colours', function () {
    $admin = themeAdmin();

    $this->actingAs($admin)->post(route('admin.themes.store'), [
        'name' => 'Made', 'css' => 'b{}',
        'tokens' => ['primary' => '#abcdef', 'nav-background' => '#123456', 'danger' => ''],
    ])->assertRedirect(route('admin.themes.index'));

    $theme = Theme::whereCode('made')->firstOrFail();
    expect($theme->tokens)->toBe(['primary' => '#abcdef', 'nav-background' => '#123456'])
        ->and($theme->css)->toBe('b{}');

    $this->actingAs($admin)->put(route('admin.themes.update', $theme), [
        'name' => 'Made', 'tokens' => ['primary' => 'notacolour', 'nav-background' => '#123456', 'danger' => 'red'],
    ])->assertSessionHasErrors(['tokens.primary', 'tokens.danger']);

    $this->actingAs($admin)->get(route('admin.themes.edit', $theme))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Themes/Edit')
            ->where('theme.id', $theme->id)
            ->where('colourTokens', fn ($tokens) => collect($tokens)->pluck('token')->contains('primary')));
    $this->actingAs($admin)->delete(route('admin.themes.destroy', $theme))->assertRedirect();
    expect(Theme::whereCode('made')->exists())->toBeFalse();
});

it('rejects theme names that slug to nothing or collide with another theme', function () {
    Theme::factory()->create(['name' => 'Taken']);
    $tokens = ['primary' => '#abcdef', 'nav-background' => '#123456'];

    $this->actingAs(themeAdmin())->post(route('admin.themes.store'), ['name' => '!!!', 'tokens' => $tokens])
        ->assertSessionHasErrors('name');
    $this->post(route('admin.themes.store'), ['name' => 'taken', 'tokens' => $tokens])
        ->assertSessionHasErrors('name');
});

it('renders the token form with a field per colour token and the saved values', function () {
    $admin = themeAdmin();
    $theme = Theme::factory()->create(['name' => 'Form', 'tokens' => ['primary' => '#abcdef', 'nav-background' => '#123456', 'danger' => '#ff0000'], 'dark_mode' => true]);

    $tokenNames = array_keys(Theme::COLOUR_TOKENS);
    $assertTokenFields = fn (Assert $page) => $page
        ->where('colourTokens', fn ($tokens) => collect($tokens)->pluck('token')->all() === $tokenNames);

    $this->actingAs($admin)->get(route('admin.themes.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $assertTokenFields($page->component('Admin/Themes/Create')));
    $this->actingAs($admin)->get(route('admin.themes.edit', $theme))->assertOk()
        ->assertInertia(fn (Assert $page) => $assertTokenFields($page->component('Admin/Themes/Edit'))
            ->where('theme.tokens.primary', '#abcdef')
            ->where('theme.tokens.danger', '#ff0000')
            ->where('theme.dark_mode', true));
});

it('serves an edited theme stylesheet instead of the cached one', function () {
    $theme = Theme::factory()->create(['name' => 'Bust', 'tokens' => ['primary' => '#111111', 'nav-background' => '#222222']]);
    $this->get($theme->stylesheetUrl())->assertSee('--mp-primary: #111111;', false);
    $oldVersion = $theme->version();

    $this->actingAs(themeAdmin())->put(route('admin.themes.update', $theme), [
        'name' => 'Bust', 'tokens' => ['primary' => '#999999', 'nav-background' => '#222222'],
    ])->assertRedirect();

    $response = $this->get(route('theme.css', 'bust'))->assertOk()
        ->assertSee('--mp-primary: #999999;', false)
        ->assertDontSee('--mp-primary: #111111;', false);
    expect($response->headers->get('ETag'))->not->toContain($oldVersion);
});

it('answers unknown theme codes from cache and serves them once the theme exists', function () {
    $this->get('/theme/ghost.css')->assertNotFound();

    DB::enableQueryLog();
    $this->get('/theme/ghost.css')->assertNotFound();
    $themeQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'themes'));
    DB::disableQueryLog();
    expect($themeQueries)->toHaveCount(0);

    $this->actingAs(themeAdmin())->post(route('admin.themes.store'), [
        'name' => 'Ghost', 'tokens' => ['primary' => '#abcdef', 'nav-background' => '#123456'],
    ])->assertRedirect();

    $this->get('/theme/ghost.css')->assertOk();
});

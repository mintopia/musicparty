<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

const THEME_TOKENS = [
    'primary', 'accent', 'danger', 'background', 'surface', 'text', 'muted', 'border',
    'sidebar', 'sidebar-text', 'sidebar-active', 'topbar', 'hero', 'hero-text',
];

const THEME_CSS = __DIR__.'/../../resources/css/theme.css';

function themeBlock(string $selector): string
{
    preg_match('/'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s', file_get_contents(THEME_CSS), $matches);

    return $matches[1] ?? '';
}

it('renders the home page as an Inertia page', function () {
    $this->withoutVite()->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('auth.user', null)
            ->where('appName', config('app.name')));
});

it('renders the login page as an Inertia page for guests', function () {
    $this->withoutVite()->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Login'));
});

it('shares the authenticated user with the shell', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => 7, 'nickname' => 'Alex']);
    $user->shouldReceive('avatarUrl')->andReturn('https://example.com/alex.png');

    $this->withoutVite()->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', 7)
            ->where('auth.user.name', 'Alex')
            ->where('auth.user.avatarUrl', 'https://example.com/alex.png')
            ->has('auth.user.avatarUrl'));
});

it('emits the colour scheme bootstrap script in the root view', function () {
    $this->withoutVite()->get(route('home'))
        ->assertSee("localStorage.getItem('colourScheme')", false)
        ->assertSee('prefers-color-scheme: dark', false);
});

it('defines every theme token for light and dark', function (string $token) {
    expect(themeBlock('@theme'))->toContain("--color-{$token}:");
    expect(themeBlock('.dark'))->toContain("--color-{$token}:");
})->with(THEME_TOKENS);

it('uses the Tabler default values', function () {
    $light = themeBlock('@theme');

    expect($light)->toContain('--color-primary: #066fd1;')
        ->and($light)->toContain('--color-background: #f6f8fb;')
        ->and($light)->toContain('--color-sidebar: #182433;');
});

it('does not define tokens outside the documented list', function () {
    preg_match_all('/--color-([a-z-]+):/', themeBlock('@theme'), $matches);

    expect($matches[1])->toEqualCanonicalizing(THEME_TOKENS);
});

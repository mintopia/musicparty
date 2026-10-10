<?php

use App\Domain\Theming\Actions\RenderThemeCss;
use App\Domain\Theming\ThemeTokens;
use App\Models\InstanceTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders only variable declarations', function (): void {
    $css = app(RenderThemeCss::class)->handle();

    expect($css)->toMatch('/\A:root\{(--[a-z-]+:[^;{}]+;)+\}\.dark\{(--[a-z-]+:[^;{}]+;)+\}\z/')
        ->and($css)->toContain('--color-primary:#066fd1;')
        ->and($css)->toContain('--font-sans:'.ThemeTokens::FONTS['inter']['stack'].';');
});

it('drops hostile stored values', function (string $hostile): void {
    InstanceTheme::factory()->create(['tokens' => [
        'light' => ['primary' => $hostile, 'accent' => '#112233'],
        'dark' => ['danger' => $hostile],
        'font' => 'url(x)',
    ]]);

    $css = app(RenderThemeCss::class)->handle();

    expect($css)->not->toContain('body')
        ->not->toContain('url(')
        ->toContain('--color-accent:#112233;')
        ->toContain('--color-primary:#066fd1;')
        ->toContain('--color-danger:#d63939;')
        ->toContain('--font-sans:'.ThemeTokens::FONTS['inter']['stack'].';');
})->with([
    'breakout' => 'red;}body{display:none}',
    'url' => 'url(x)',
    'named colour' => 'red',
    'short hex' => '#fff',
    'trailing' => "#ffffff;\n",
]);

it('emits the stored override after the vite assets in the page', function (): void {
    InstanceTheme::factory()->create(['tokens' => ['light' => ['primary' => '#123456'], 'dark' => [], 'font' => 'mono']]);

    $html = $this->withoutVite()->get('/')->getContent();

    expect($html)->toContain('--color-primary:#123456;')
        ->and($html)->toContain('--font-sans:'.ThemeTokens::FONTS['mono']['stack'].';');
    preg_match_all('/<style>(.*?)<\/style>/s', $html, $blocks);
    $themeBlock = collect($blocks[1])->first(fn (string $b): bool => str_contains($b, '--color-primary'));
    expect($themeBlock)->toBe(app(RenderThemeCss::class)->handle());
});

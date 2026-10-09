<?php

use App\Domain\Theming\Contrast;
use App\Domain\Theming\ContrastWarnings;
use App\Domain\Theming\ThemeTokens;

it('computes known ratios', function (string $fg, string $bg, float $expected): void {
    expect(round(Contrast::ratio($fg, $bg), 2))->toBe($expected);
})->with([
    'black on white' => ['#000000', '#ffffff', 21.0],
    'white on black' => ['#ffffff', '#000000', 21.0],
    'same colour' => ['#336699', '#336699', 1.0],
    'grey 777 on white' => ['#777777', '#ffffff', 4.48],
]);

it('applies the AA threshold', function (string $fg, string $bg, bool $passes): void {
    expect(Contrast::meetsAA($fg, $bg))->toBe($passes);
})->with([
    'default text on white' => ['#182433', '#ffffff', true],
    'black on white' => ['#000000', '#ffffff', true],
    'grey 777 on white just fails' => ['#777777', '#ffffff', false],
    'grey 767676 on white just passes' => ['#767676', '#ffffff', true],
    'same colour' => ['#abcdef', '#abcdef', false],
]);

it('reports no warnings for the default theme', function (): void {
    expect((new ContrastWarnings)->for(ThemeTokens::defaults()))->toBe([]);
});

it('warns for failing text pairs in either scheme', function (): void {
    $theme = ThemeTokens::defaults();
    $theme['light']['text'] = '#777777';
    $theme['dark']['surface'] = '#dce1e7';

    $warnings = (new ContrastWarnings)->for($theme);

    expect($warnings)->toHaveCount(3)
        ->and($warnings[0])->toBe(['scheme' => 'light', 'pair' => 'text/background', 'ratio' => 4.21])
        ->and(array_column($warnings, 'pair'))->toContain('text/surface')
        ->and(array_column($warnings, 'scheme'))->toContain('dark');
});

it('exposes defaults matching theme.css', function (): void {
    $css = file_get_contents(__DIR__.'/../../../resources/css/theme.css');
    preg_match('/@theme \{(.*?)\}/s', $css, $light);
    preg_match('/\.dark \{(.*?)\}/s', $css, $dark);
    $parse = function (string $block): array {
        preg_match_all('/--color-([a-z-]+):\s*(#[0-9a-f]{6});/', $block, $m);

        return array_combine($m[1], $m[2]);
    };

    expect($parse($light[1]))->toBe(ThemeTokens::defaults()['light'])
        ->and($parse($dark[1]))->toBe(ThemeTokens::defaults()['dark'])
        ->and($css)->toContain('--font-sans: '.ThemeTokens::FONTS['inter']['stack'].';')
        ->and(array_keys(ThemeTokens::defaults()['light']))->toBe(ThemeTokens::keys());
});

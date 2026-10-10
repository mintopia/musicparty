<?php

use App\Domain\Theming\ThemeTokens;

it('limits party overrides to the six colour tokens', function (): void {
    expect(ThemeTokens::partyKeys())->toBe(['primary', 'accent', 'background', 'surface', 'text', 'danger'])
        ->and(array_diff(ThemeTokens::partyKeys(), ThemeTokens::keys()))->toBe([])
        ->and(ThemeTokens::partyTokenList())->toHaveCount(6);
});

it('offers a fixed set of tv layouts including the default', function (): void {
    expect(ThemeTokens::tvLayoutKeys())->toContain(ThemeTokens::DEFAULT_TV_LAYOUT)
        ->and(ThemeTokens::tvLayoutList()[0])->toBe(['value' => 'default', 'label' => 'Default']);
});

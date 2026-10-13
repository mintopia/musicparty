<?php

function tokenSource(): string
{
    return (string) file_get_contents(base_path('resources/sass/_tokens.scss'));
}

it('does not use light-dark() in the token source', function () {
    expect(tokenSource())->not->toContain('light-dark(');
});

it('defines no themeable colour default at :root', function () {
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', tokenSource(), $blocks, PREG_SET_ORDER);

    $offending = [];
    foreach ($blocks as [, $selector, $body]) {
        if (preg_match('/(^|,)\s*(:root|:host)\s*(,|$)/', trim($selector)) !== 1) {
            continue;
        }
        preg_match_all('/--mp-(nav-background|surface|text|border)\s*:/', $body, $found);
        $offending = [...$offending, ...$found[1]];
    }

    expect($offending)->toBe([])
        ->and(tokenSource())->toContain('[data-bs-theme=dark]');
});

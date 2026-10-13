<?php

$themedTokens = ['nav-background', 'surface', 'text', 'border'];

it('does not use light-dark() in the token source', function () {
    expect(file_get_contents(base_path('resources/sass/_tokens.scss')))->not->toContain('light-dark(');
});

it('defines no themeable colour default at :root', function () use ($themedTokens) {
    $source = file_get_contents(base_path('resources/sass/_tokens.scss'));
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $source, $blocks, PREG_SET_ORDER);

    foreach ($blocks as [, $selector, $body]) {
        $atRoot = preg_match('/(^|,)\s*(:root|:host)\s*(,|$)/', trim($selector));
        foreach ($themedTokens as $token) {
            if ($atRoot && preg_match('/--mp-'.$token.'\s*:/', $body)) {
                $this->fail("--mp-{$token} must not be defined at :root; a saved theme could not override it per scope.");
            }
        }
    }

    expect($source)->toContain('[data-bs-theme=dark]');
});

it('keeps light-dark fallbacks out of the built CSS', function () {
    $files = glob(public_path('build/assets/*.css')) ?: [];
    if ($files === []) {
        $this->markTestSkipped('No built CSS; run npm run build.');
    }

    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toMatch('/--mp-(nav-background|surface|text|border)\s*:[^;}]*lightningcss/');
    }
});

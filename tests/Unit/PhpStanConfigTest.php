<?php

it('keeps the PHPStan baseline free of missingType entries', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/phpstan-baseline.neon'))->not->toContain('missingType');
});

it('analyses the tests directory with PHPStan', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/phpstan.neon'))->toMatch('/^\s+- tests$/m');
});

it('fails CI when the baseline regains missingType entries', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/.github/workflows/ci.yml'))->toContain('! grep -q missingType phpstan-baseline.neon');
});
